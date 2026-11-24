<?php

namespace App\Services;

use App\Libraries\Money;
use App\Libraries\PaymentGateway;

/**
 * Wallet top-ups with GCash / Maya through a hosted checkout
 * (Xendit, or PayMongo as a fallback), TEST MODE ONLY.
 *
 * Flow:
 *   1. start()     → pending wallet_topups row + provider checkout → redirect.
 *   2. confirm()   → called from the verified webhook, or from reconcile()
 *                    after the provider confirms server-to-server; credits
 *                    the wallet exactly once.
 * The browser redirect itself is never treated as proof of payment.
 */
class TopUpService extends TransactionalService
{
    /** Provider code => config class, in order of preference. */
    private const PROVIDERS = ['xendit' => 'Xendit', 'paymongo' => 'PayMongo'];

    private const LABELS = ['xendit' => 'Xendit', 'paymongo' => 'PayMongo'];

    /** The configured provider used for new top-ups, or null for demo credits. */
    public static function activeProvider(): ?string
    {
        foreach (self::PROVIDERS as $name => $configClass) {
            if (config($configClass)->isConfigured()) {
                return $name;
            }
        }

        return null;
    }

    public static function providerLabel(?string $name): string
    {
        return self::LABELS[$name] ?? 'online payment';
    }

    /** @return array{0: int, 1: int} min and max top-up in centavos */
    public static function limits(string $provider): array
    {
        $config = config(self::PROVIDERS[$provider]);

        return [$config->minTopUpCents, $config->maxTopUpCents];
    }

    private function gateway(string $provider): PaymentGateway
    {
        if (! isset(self::PROVIDERS[$provider])) {
            throw new ServiceException('Unknown payment provider.');
        }

        return service($provider);
    }

    /** @return string checkout URL to redirect the user to */
    public function start(int $userId, string $amountInput): string
    {
        $provider = self::activeProvider() ?? throw new ServiceException('Online payments are not configured.');
        [$min, $max] = self::limits($provider);

        try {
            $cents = Money::toCents($amountInput);
        } catch (\InvalidArgumentException $e) {
            throw new ServiceException($e->getMessage());
        }

        if ($cents < $min || $cents > $max) {
            throw new ServiceException('Enter an amount from ' . Money::format($min) . ' to ' . Money::format($max) . '.');
        }

        $this->db->table('wallet_topups')->insert([
            'user_id'    => $userId,
            'amount'     => Money::toDecimal($cents),
            'provider'   => $provider,
            'status'     => 'pending',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $topUpId = (int) $this->db->insertID();

        try {
            $checkout = $this->gateway($provider)->createCheckout(
                $cents,
                'Pasa-Buy wallet top-up',
                'PB-TOPUP-' . $topUpId,
                $this->returnUrl($provider, 'wallet/top-up/return?topup=' . $topUpId),
                $this->returnUrl($provider, 'wallet?topup=cancelled'),
                ['topup_id' => (string) $topUpId, 'user_id' => (string) $userId],
            );
        } catch (ServiceException $e) {
            $this->db->table('wallet_topups')->where('id', $topUpId)->update(['status' => 'failed']);

            throw $e;
        }

        $this->db->table('wallet_topups')->where('id', $topUpId)->update(['checkout_session_id' => $checkout['id']]);

        return $checkout['checkout_url'];
    }

    /**
     * Xendit needs HTTPS return URLs, so a public tunnel URL can be set in
     * xendit.publicBaseUrl; otherwise the normal base URL is used (and the
     * client drops it if it isn't HTTPS).
     */
    private function returnUrl(string $provider, string $path): string
    {
        $public = $provider === 'xendit' ? trim(config('Xendit')->publicBaseUrl) : '';

        return $public !== '' ? rtrim($public, '/') . '/' . $path : base_url($path);
    }

    /**
     * Credits a verified payment. Returns 'credited', 'already' or 'unknown'.
     * Safe to call any number of times for the same checkout.
     *
     * @param string|null $provider when given, the top-up must belong to it
     */
    public function confirm(string $checkoutId, int $paidCents, string $paymentId, string $method, bool $livemode = false, ?string $provider = null): string
    {
        return $this->transaction(function () use ($checkoutId, $paidCents, $paymentId, $method, $livemode, $provider): string {
            $topUp = $this->db->query('SELECT * FROM `wallet_topups` WHERE `checkout_session_id` = ? FOR UPDATE', [$checkoutId])->getRowArray();

            if ($topUp === null || ($provider !== null && $topUp['provider'] !== $provider)) {
                return 'unknown';
            }

            if ($topUp['status'] === 'paid') {
                return 'already';
            }

            $expected = Money::toCents($topUp['amount']);
            $gateway  = $this->gateway($topUp['provider']);

            if ($paidCents !== $expected) {
                log_message('critical', '{g} amount mismatch for {s}: expected {e}, got {p}', ['g' => $topUp['provider'], 's' => $checkoutId, 'e' => $expected, 'p' => $paidCents]);

                throw new ServiceException('Payment amount does not match the top-up.');
            }

            $userId = (int) $topUp['user_id'];
            $user   = $this->lockUsers($userId)[$userId];
            $label  = match ($method) { 'gcash' => 'GCash', 'paymaya', 'maya' => 'Maya', default => ucfirst($method) };

            $this->postLedgerEntry(
                $userId,
                Money::toCents($user['wallet_balance']),
                'top_up',
                'credit',
                $expected,
                $method !== '' ? substr($method, 0, 30) : $topUp['provider'],
                'Wallet top-up via ' . $label . ($livemode ? '' : ' (' . self::providerLabel($topUp['provider']) . ' test mode, not real money)'),
                null,
                null,
                $gateway->keyPrefix() . $checkoutId,
            );

            $this->db->table('wallet_topups')->where('id', $topUp['id'])->update([
                'status'         => 'paid',
                'payment_id'     => substr($paymentId, 0, 64),
                'payment_method' => substr($method, 0, 30),
                'livemode'       => $livemode ? 1 : 0,
                'paid_at'        => date('Y-m-d H:i:s'),
            ]);

            return 'credited';
        });
    }

    /**
     * Asks the provider (server-to-server) whether the top-up was paid, and
     * credits if so. Used when the user returns from checkout or presses
     * "Check payment", so local setups work even when the webhook can't
     * reach localhost.
     *
     * @return 'credited'|'already'|'pending'|'unknown'
     */
    public function reconcile(int $topUpId, int $userId): string
    {
        $topUp = $this->db->table('wallet_topups')->where('id', $topUpId)->where('user_id', $userId)->get()->getRowArray();

        if ($topUp === null || empty($topUp['checkout_session_id'])) {
            return 'unknown';
        }

        if ($topUp['status'] === 'paid') {
            return 'already';
        }

        $paid = $this->gateway($topUp['provider'])->fetchPaid($topUp['checkout_session_id']);

        if ($paid === null) {
            return 'pending';
        }

        return $this->confirm($topUp['checkout_session_id'], $paid['amount_cents'], $paid['payment_id'], $paid['method'], $paid['livemode'], $topUp['provider']);
    }

    /** Recent unpaid top-ups the user can still check on. */
    public function pendingFor(int $userId): array
    {
        return $this->db->table('wallet_topups')
            ->where('user_id', $userId)
            ->where('status', 'pending')
            ->where('checkout_session_id IS NOT NULL')
            ->where('created_at >=', date('Y-m-d H:i:s', time() - 2 * 86400))
            ->orderBy('id', 'DESC')
            ->limit(5)
            ->get()->getResultArray();
    }
}

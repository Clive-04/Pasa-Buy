<?php

namespace App\Services;

use App\Libraries\Money;
use App\Libraries\PayMongoClient;

/**
 * Wallet top-ups through PayMongo (GCash / Maya), TEST MODE ONLY.
 *
 * Flow:
 *   1. start()     → pending wallet_topups row + PayMongo checkout session → redirect.
 *   2. confirm()   → called from the signed webhook, or from reconcile()
 *                    after the user returns; credits the wallet exactly once.
 * The browser redirect itself is never treated as proof of payment.
 */
class TopUpService extends TransactionalService
{
    private ?PayMongoClient $client = null;

    private function client(): PayMongoClient
    {
        return $this->client ??= service('paymongo');
    }

    /** @return string checkout URL to redirect the user to */
    public function start(int $userId, string $amountInput): string
    {
        $config = config('PayMongo');

        try {
            $cents = Money::toCents($amountInput);
        } catch (\InvalidArgumentException $e) {
            throw new ServiceException($e->getMessage());
        }

        if ($cents < $config->minTopUpCents || $cents > $config->maxTopUpCents) {
            throw new ServiceException('Enter an amount from ' . Money::format($config->minTopUpCents) . ' to ' . Money::format($config->maxTopUpCents) . '.');
        }

        $this->db->table('wallet_topups')->insert([
            'user_id'    => $userId,
            'amount'     => Money::toDecimal($cents),
            'provider'   => 'paymongo',
            'status'     => 'pending',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $topUpId = (int) $this->db->insertID();

        try {
            $session = $this->client()->createCheckoutSession(
                $cents,
                'Pasa-Buy wallet top-up',
                base_url('wallet/top-up/return?topup=' . $topUpId),
                base_url('wallet?topup=cancelled'),
                'PB-TOPUP-' . $topUpId,
                ['topup_id' => (string) $topUpId, 'user_id' => (string) $userId],
            );
        } catch (ServiceException $e) {
            $this->db->table('wallet_topups')->where('id', $topUpId)->update(['status' => 'failed']);

            throw $e;
        }

        $this->db->table('wallet_topups')->where('id', $topUpId)->update(['checkout_session_id' => $session['id']]);

        return $session['checkout_url'];
    }

    /**
     * Credits a verified payment. Returns 'credited', 'already' or 'unknown'.
     * Safe to call any number of times for the same session.
     */
    public function confirm(string $sessionId, int $paidCents, string $paymentId, string $method, bool $livemode = false): string
    {
        return $this->transaction(function () use ($sessionId, $paidCents, $paymentId, $method, $livemode): string {
            $topUp = $this->db->query('SELECT * FROM `wallet_topups` WHERE `checkout_session_id` = ? FOR UPDATE', [$sessionId])->getRowArray();

            if ($topUp === null) {
                return 'unknown';
            }

            if ($topUp['status'] === 'paid') {
                return 'already';
            }

            $expected = Money::toCents($topUp['amount']);

            if ($paidCents !== $expected) {
                log_message('critical', 'PayMongo amount mismatch for {s}: expected {e}, got {p}', ['s' => $sessionId, 'e' => $expected, 'p' => $paidCents]);

                throw new ServiceException('Payment amount does not match the top-up.');
            }

            $userId = (int) $topUp['user_id'];
            $user   = $this->lockUsers($userId)[$userId];
            $label  = match ($method) { 'gcash' => 'GCash', 'paymaya' => 'Maya', default => ucfirst($method) };

            $this->postLedgerEntry(
                $userId,
                Money::toCents($user['wallet_balance']),
                'top_up',
                'credit',
                $expected,
                $method !== '' ? substr($method, 0, 30) : 'paymongo',
                'Wallet top-up via ' . $label . ($livemode ? '' : ' (PayMongo test mode, not real money)'),
                null,
                null,
                'pm_' . $sessionId,
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
     * After the user returns from checkout: ask PayMongo (server-to-server)
     * whether the session was paid, and credit if so. Lets local setups work
     * even when the webhook can't reach localhost.
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

        $session = $this->client()->retrieveCheckoutSession($topUp['checkout_session_id']);
        $paid    = PayMongoClient::paidPayment($session);

        if ($paid === null) {
            return 'pending';
        }

        return $this->confirm($topUp['checkout_session_id'], $paid['amount'], $paid['id'], $paid['method'], (bool) ($session['attributes']['livemode'] ?? false));
    }
}

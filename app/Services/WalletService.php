<?php

namespace App\Services;

use App\Libraries\FeePolicy;
use App\Libraries\Money;

/**
 * Wallet operations. Balances are SIMULATED DEMO CREDITS — the release has
 * no verified payment provider, so no top-up is ever presented as a real
 * GCash/Maya/bank payment, and user-entered references are not accepted.
 */
class WalletService extends TransactionalService
{
    /**
     * Adds demo credits. $idempotencyKey comes from a hidden field rendered
     * with the form; resubmitting the same form (double click, refresh,
     * concurrent requests) credits the wallet only once.
     *
     * @return array{credited: bool, balanceCents: int}
     */
    public function addDemoCredits(int $userId, string $amountInput, string $idempotencyKey): array
    {
        $config = config('PasaBuy');

        if (! $config->demoMode) {
            throw new ServiceException('Wallet top-ups are unavailable: no verified payment method is configured.');
        }

        if (! preg_match('/^[a-f0-9]{32}$/', $idempotencyKey)) {
            throw new ServiceException('This form has expired. Reload the page and try again.');
        }

        try {
            $amountCents = Money::toCents($amountInput);
        } catch (\InvalidArgumentException $e) {
            throw new ServiceException($e->getMessage());
        }

        if ($amountCents <= 0 || $amountCents > $config->demoTopUpMaxCents) {
            throw new ServiceException('Enter an amount from ₱0.01 to ' . Money::format($config->demoTopUpMaxCents) . '.');
        }

        return $this->transaction(function () use ($userId, $amountCents, $idempotencyKey, $config): array {
            $user = $this->lockUsers($userId)[$userId];

            $existing = $this->db->table('payments')
                ->where('idempotency_key', $idempotencyKey)
                ->get()
                ->getRowArray();

            if ($existing !== null) {
                if ((int) $existing['user_id'] !== $userId) {
                    throw new ServiceException('This form has expired. Reload the page and try again.');
                }

                return ['credited' => false, 'balanceCents' => Money::toCents($user['wallet_balance'])];
            }

            $balance = Money::toCents($user['wallet_balance']);

            if ($balance + $amountCents > $config->demoWalletCapCents) {
                throw new ServiceException('Demo wallets are capped at ' . Money::format($config->demoWalletCapCents) . '.');
            }

            $newBalance = $this->postLedgerEntry(
                $userId,
                $balance,
                'demo_top_up',
                'credit',
                $amountCents,
                'demo',
                'Demo credits added (simulated, not real money)',
                null,
                null,
                $idempotencyKey,
            );

            return ['credited' => true, 'balanceCents' => $newBalance];
        });
    }

    /**
     * Requester pays the assigned runner from their wallet:
     *   requester −(item + service fee); runner +(item + service fee);
     *   runner −platform fee. All three entries commit together or not at all.
     */
    public function payForRequest(int $requestId, int $requesterId): void
    {
        $this->transaction(function () use ($requestId, $requesterId): void {
            $request = $this->lockRow('requests', $requestId);

            if ($request === null) {
                throw new ServiceException('Request not found.');
            }

            if ((int) $request['user_id'] !== $requesterId) {
                throw new ServiceException('Only the requester can make this payment.');
            }

            if ($request['payment_method'] === 'cod') {
                throw new ServiceException('Cash-on-delivery requests are paid in person, not through the wallet.');
            }

            if ($request['payment_status'] === 'paid') {
                throw new ServiceException('This request is already paid.');
            }

            if (! in_array($request['status'], ['accepted', 'on_the_way'], true)) {
                throw new ServiceException('You can pay once a runner has accepted the request.');
            }

            $acceptance = $this->lockAcceptance($requestId);

            if ($acceptance === null || ! in_array($acceptance['status'], ['accepted', 'on_the_way'], true)) {
                throw new ServiceException('No runner is assigned to this request.');
            }

            $runnerId = (int) $acceptance['runner_id'];
            $users    = $this->lockUsers($requesterId, $runnerId);

            $itemCents  = Money::toCents($request['estimated_price']);
            $feeCents   = Money::toCents($request['service_fee']);
            $totalCents = FeePolicy::requesterTotalCents($itemCents, $feeCents);
            $platform   = Money::toCents($request['platform_fee']);

            if ($totalCents <= 0) {
                throw new ServiceException('This request has no amount to pay.');
            }

            if (Money::toCents($users[$requesterId]['wallet_balance']) < $totalCents) {
                throw new ServiceException('Insufficient wallet balance. You need ' . Money::format($totalCents) . '.');
            }

            $this->postLedgerEntry(
                $requesterId,
                Money::toCents($users[$requesterId]['wallet_balance']),
                'request_payment',
                'debit',
                $totalCents,
                'wallet',
                'Paid runner for request #' . $requestId . ' (item + service fee)',
                $requestId,
                $runnerId,
            );

            $runnerBalance = $this->postLedgerEntry(
                $runnerId,
                Money::toCents($users[$runnerId]['wallet_balance']),
                'request_earning',
                'credit',
                $totalCents,
                'wallet',
                'Received payment for request #' . $requestId . ' (item + service fee)',
                $requestId,
                $requesterId,
            );

            if ($platform > 0) {
                $this->postLedgerEntry(
                    $runnerId,
                    $runnerBalance,
                    'platform_fee',
                    'debit',
                    $platform,
                    'wallet',
                    'Platform fee (10% of service fee) for request #' . $requestId,
                    $requestId,
                );
            }

            $this->db->table('requests')->where('id', $requestId)->update([
                'payment_status' => 'paid',
                'paid_at'        => date('Y-m-d H:i:s'),
            ]);

            $this->notify(
                $runnerId,
                $requesterId,
                $requestId,
                'runner_paid',
                'Requester paid you',
                'The requester paid ' . Money::format($totalCents) . ' for request #' . $requestId
                . '. A platform fee of ' . Money::format($platform) . ' was deducted. You can now complete the delivery.'
            );
        });
    }
}

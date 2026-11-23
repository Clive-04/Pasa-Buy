<?php

namespace App\Services;

use App\Libraries\FeePolicy;
use App\Libraries\Money;

/**
 * Request state machine.
 *
 *   pending ──accept──▶ accepted ──on the way──▶ on_the_way ──deliver──▶ delivered
 *      │                    │                        │
 *      └─requester/admin──▶ cancelled ◀──admin (only while unpaid)──┘
 *
 * Every transition locks the request row first, re-checks its current state
 * inside the transaction, and writes the status change, ledger entries and
 * notifications together, so concurrent or repeated submissions cannot
 * double-accept, double-settle or leave a half-finished delivery.
 */
class RequestLifecycle extends TransactionalService
{
    public const CATEGORIES      = ['food', 'medicine', 'school_supplies', 'household', 'personal_care', 'other'];
    public const URGENCIES       = ['normal', 'urgent', 'asap'];
    public const PAYMENT_METHODS = ['wallet', 'cod'];

    /**
     * Creates a pending request. $input must already be validated for
     * presence/length by the controller; amounts are re-validated here.
     *
     * @param array<string, mixed> $input
     */
    public function create(int $requesterId, array $input): int
    {
        $config = config('PasaBuy');

        try {
            $itemCents = Money::toCents((string) ($input['estimated_price'] ?? '0'));
            $feeCents  = Money::toCents((string) ($input['service_fee'] ?? '0'));
        } catch (\InvalidArgumentException $e) {
            throw new ServiceException($e->getMessage());
        }

        if ($itemCents > $config->maxItemPriceCents) {
            throw new ServiceException('Estimated price is above the ' . Money::format($config->maxItemPriceCents) . ' limit.');
        }

        if ($feeCents > $config->maxServiceFeeCents) {
            throw new ServiceException('Service fee is above the ' . Money::format($config->maxServiceFeeCents) . ' limit.');
        }

        $paymentMethod = (string) ($input['payment_method'] ?? '');

        if (! in_array($paymentMethod, self::PAYMENT_METHODS, true)) {
            throw new ServiceException('Choose a valid payment method.');
        }

        $this->db->table('requests')->insert([
            'user_id'          => $requesterId,
            'item_name'        => $input['item_name'],
            'category'         => $input['category'],
            'quantity'         => (int) $input['quantity'],
            'brand'            => $input['brand'] ?: null,
            'item_notes'       => $input['item_notes'] ?: null,
            'store_name'       => $input['store_name'],
            'alt_store'        => $input['alt_store'] ?: null,
            'store_address'    => $input['store_address'],
            'delivery_address' => $input['delivery_address'],
            'contact_number'   => $input['contact_number'],
            'landmark'         => $input['landmark'] ?: null,
            'delivery_notes'   => $input['delivery_notes'] ?: null,
            'estimated_price'  => Money::toDecimal($itemCents),
            'service_fee'      => Money::toDecimal($feeCents),
            'platform_fee'     => Money::toDecimal(FeePolicy::platformFeeCents($feeCents)),
            'total_amount'     => Money::toDecimal(FeePolicy::requesterTotalCents($itemCents, $feeCents)),
            'payment_method'   => $paymentMethod,
            'payment_status'   => $paymentMethod === 'cod' ? 'cod_pending' : 'unpaid',
            'urgency'          => $input['urgency'],
            'status'           => 'pending',
            'created_at'       => date('Y-m-d H:i:s'),
            'updated_at'       => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    public function accept(int $requestId, int $runnerId): void
    {
        $this->transaction(function () use ($requestId, $runnerId): void {
            $request = $this->lockRow('requests', $requestId);

            if ($request === null) {
                throw new ServiceException('Request not found.');
            }

            if ((int) $request['user_id'] === $runnerId) {
                throw new ServiceException('You cannot accept your own request.');
            }

            if ($request['status'] !== 'pending') {
                throw new ServiceException('This request is no longer available. Another runner may have accepted it.');
            }

            $runner = $this->db->table('users')->where('id', $runnerId)->get()->getRowArray();

            if ($runner === null) {
                throw new ServiceException('Account not found.');
            }

            $requesterStatus = $this->db->table('users')->select('status')->where('id', (int) $request['user_id'])->get()->getRow('status');

            if ($requesterStatus === 'suspended') {
                throw new ServiceException('This request is no longer available.');
            }

            if ($request['payment_method'] === 'cod') {
                $feeCents = Money::toCents($request['platform_fee']);

                if (Money::toCents($runner['wallet_balance']) < $feeCents) {
                    throw new ServiceException(
                        'This is a cash-on-delivery request. Your wallet needs at least ' . Money::format($feeCents)
                        . ' to cover the platform fee charged when you complete it.'
                    );
                }
            }

            $this->db->table('requests')
                ->where('id', $requestId)
                ->where('status', 'pending')
                ->update(['status' => 'accepted', 'updated_at' => date('Y-m-d H:i:s')]);

            if ($this->db->affectedRows() !== 1) {
                throw new ServiceException('This request is no longer available.');
            }

            // UNIQUE(request_id) is the database-level backstop.
            $this->db->table('accepted_requests')->insert([
                'request_id'  => $requestId,
                'runner_id'   => $runnerId,
                'status'      => 'accepted',
                'accepted_at' => date('Y-m-d H:i:s'),
            ]);

            $this->notify(
                (int) $request['user_id'],
                $runnerId,
                $requestId,
                'request_accepted',
                'Your request was accepted',
                $this->displayName($runner) . ' accepted your request for ' . $request['item_name'] . '.'
            );
        });
    }

    public function markOnTheWay(int $requestId, int $runnerId): void
    {
        $this->transaction(function () use ($requestId, $runnerId): void {
            [$request, $acceptance] = $this->lockForRunner($requestId, $runnerId);

            if ($request['status'] !== 'accepted' || $acceptance['status'] !== 'accepted') {
                throw new ServiceException('Only accepted requests can be marked as on the way.');
            }

            $this->setStatus($requestId, (int) $acceptance['id'], 'on_the_way');

            $this->notify(
                (int) $request['user_id'],
                $runnerId,
                $requestId,
                'on_the_way',
                'Your request is on the way',
                'The runner marked your request #' . $requestId . ' as on the way.'
            );
        });
    }

    /**
     * Completes delivery. For cash on delivery the runner must confirm that
     * the requester paid in cash; the platform fee is then debited from the
     * runner's wallet in the same transaction.
     */
    public function markDelivered(int $requestId, int $runnerId, bool $cashConfirmed): void
    {
        $this->transaction(function () use ($requestId, $runnerId, $cashConfirmed): void {
            [$request, $acceptance] = $this->lockForRunner($requestId, $runnerId);

            if ($request['status'] === 'delivered') {
                throw new ServiceException('This request is already marked as delivered.');
            }

            if ($request['status'] !== 'on_the_way' || $acceptance['status'] !== 'on_the_way') {
                throw new ServiceException('Mark the request as On the Way before Delivered.');
            }

            $isCod = $request['payment_method'] === 'cod';

            if ($isCod) {
                if (! $cashConfirmed) {
                    throw new ServiceException('Confirm that you received the cash payment first.');
                }

                if ($request['payment_status'] !== 'cod_pending') {
                    throw new ServiceException('This cash payment was already settled.');
                }

                $feeCents = Money::toCents($request['platform_fee']);
                $runner   = $this->lockUsers($runnerId)[$runnerId];

                if ($feeCents > 0) {
                    $balance = Money::toCents($runner['wallet_balance']);

                    if ($balance < $feeCents) {
                        throw new ServiceException(
                            'Your wallet needs ' . Money::format($feeCents) . ' for the platform fee before you can complete this delivery. Add demo credits, then try again.'
                        );
                    }

                    $this->postLedgerEntry(
                        $runnerId,
                        $balance,
                        'platform_fee',
                        'debit',
                        $feeCents,
                        'cod',
                        'Platform fee (10% of service fee) for request #' . $requestId,
                        $requestId,
                    );
                }

                $this->db->table('requests')->where('id', $requestId)->update([
                    'payment_status' => 'paid_cod',
                    'paid_at'        => date('Y-m-d H:i:s'),
                ]);
            } elseif ($request['payment_status'] !== 'paid') {
                throw new ServiceException('The requester must pay through the wallet before you can mark this as delivered.');
            }

            $this->setStatus($requestId, (int) $acceptance['id'], 'delivered');

            $this->notify(
                (int) $request['user_id'],
                $runnerId,
                $requestId,
                'delivered',
                'Your request was delivered',
                'The runner marked your request #' . $requestId . ' as delivered.'
            );

            if ($isCod) {
                $this->notify(
                    (int) $request['user_id'],
                    $runnerId,
                    $requestId,
                    'cod_paid',
                    'Cash payment confirmed',
                    'The runner confirmed receiving your cash payment of ' . Money::formatDecimal($request['total_amount'])
                    . ' for request #' . $requestId . '.'
                );
            }
        });
    }

    /**
     * Requesters may cancel their own pending requests. Admins may also
     * cancel accepted / on-the-way requests that have not been settled.
     * Settled or delivered requests are never cancelled (no refunds exist).
     */
    public function cancel(int $requestId, int $actorId, bool $asAdmin = false): void
    {
        $this->transaction(function () use ($requestId, $actorId, $asAdmin): void {
            $request = $this->lockRow('requests', $requestId);

            if ($request === null) {
                throw new ServiceException('Request not found.');
            }

            if (! $asAdmin && (int) $request['user_id'] !== $actorId) {
                throw new ServiceException('You can only cancel your own requests.');
            }

            if (in_array($request['payment_status'], ['paid', 'paid_cod'], true) || $request['status'] === 'delivered') {
                throw new ServiceException('Paid or delivered requests cannot be cancelled.');
            }

            $allowed = $asAdmin ? ['pending', 'accepted', 'on_the_way'] : ['pending'];

            if (! in_array($request['status'], $allowed, true)) {
                throw new ServiceException(
                    $request['status'] === 'cancelled'
                        ? 'This request is already cancelled.'
                        : 'A runner has already accepted this request. Message them or contact an admin to cancel.'
                );
            }

            $acceptance = $this->lockAcceptance($requestId);

            $this->db->table('requests')->where('id', $requestId)->update([
                'status'       => 'cancelled',
                'cancelled_at' => date('Y-m-d H:i:s'),
                'updated_at'   => date('Y-m-d H:i:s'),
            ]);

            if ($acceptance !== null) {
                $this->db->table('accepted_requests')->where('id', $acceptance['id'])->update(['status' => 'cancelled']);

                $this->notify(
                    (int) $acceptance['runner_id'],
                    $actorId,
                    $requestId,
                    'request_cancelled',
                    'A request you accepted was cancelled',
                    'Request #' . $requestId . ' (' . $request['item_name'] . ') was cancelled' . ($asAdmin ? ' by an administrator.' : '.')
                );
            }

            if ($asAdmin && (int) $request['user_id'] !== $actorId) {
                $this->notify(
                    (int) $request['user_id'],
                    $actorId,
                    $requestId,
                    'request_cancelled',
                    'Your request was cancelled',
                    'An administrator cancelled your request #' . $requestId . ' (' . $request['item_name'] . ').'
                );
            }
        });
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function lockForRunner(int $requestId, int $runnerId): array
    {
        $request = $this->lockRow('requests', $requestId);

        if ($request === null) {
            throw new ServiceException('Request not found.');
        }

        $acceptance = $this->lockAcceptance($requestId);

        if ($acceptance === null || (int) $acceptance['runner_id'] !== $runnerId) {
            throw new ServiceException('Accepted request not found.');
        }

        if ($request['status'] === 'cancelled') {
            throw new ServiceException('This request was cancelled.');
        }

        return [$request, $acceptance];
    }

    private function setStatus(int $requestId, int $acceptanceId, string $status): void
    {
        $this->db->table('accepted_requests')->where('id', $acceptanceId)->update(['status' => $status]);
        $this->db->table('requests')->where('id', $requestId)->update([
            'status'     => $status,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }
}

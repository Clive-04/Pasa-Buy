<?php

namespace App\Services;

use App\Libraries\FeePolicy;
use App\Libraries\Money;

/**
 * R5: the actual item price, proven by a private receipt and approved by
 * the requester, is what gets paid.
 *
 * Lifecycle (requests.price_status):
 *
 *   awaiting_receipt ──runner submits──▶ submitted ──requester approves──▶ approved ──paid──▶ (frozen)
 *          ▲                               │  ▲                              │
 *          │                    rejects ◀──┘  └──runner submits again◀───────┘ (cancels the approval)
 *          └── rejected ──runner submits again──▶ submitted
 *
 * Rules:
 *  - only the assigned runner submits, while the errand is accepted or on
 *    the way and nothing has been paid; a receipt is required;
 *  - a new submission supersedes any pending or approved one, so an approval
 *    can never be changed silently: the requester must approve again;
 *  - the requester decides on the latest submission only (by id), so an
 *    approval can't land on a price the runner has since revised;
 *  - rejection needs a reason; the errand stays open for a revised price,
 *    or an admin can cancel it (no refunds are implied: nothing was paid);
 *  - approval sets total_amount = approved item price + service fee; the
 *    platform fee (10% of the service fee) is unchanged;
 *  - payment (wallet or cash on delivery) requires 'approved'; once paid,
 *    submissions are refused, so the paid amount is frozen;
 *  - 'not_required' marks requests settled before this feature: they are
 *    never asked for approval and no evidence is invented.
 *
 * Every step locks the request row, so it is serialised with payment.
 */
class PriceApproval extends TransactionalService
{
    public const REASON_MIN = 3;
    public const REASON_MAX = 255;
    public const NOTE_MAX   = 255;

    /**
     * Records a new price submission. The receipt file must already be
     * stored privately; the caller deletes it if this throws.
     *
     * @param array{path: string, name: string, type: string} $receipt
     */
    public function submit(int $requestId, int $runnerId, string $amountInput, array $receipt, string $note = ''): int
    {
        try {
            $itemCents = Money::toCents($amountInput);
        } catch (\InvalidArgumentException $e) {
            throw new ServiceException('Enter the actual item amount, for example 245.50.');
        }

        $maxCents = config('PasaBuy')->maxItemPriceCents;

        if ($itemCents <= 0 || $itemCents > $maxCents) {
            throw new ServiceException('Enter an item amount from ₱0.01 to ' . Money::format($maxCents) . '.');
        }

        if (($receipt['path'] ?? '') === '') {
            throw new ServiceException('Attach a photo or PDF of the receipt.');
        }

        $note = trim(preg_replace('/\s+/', ' ', $note) ?? '');

        if (mb_strlen($note) > self::NOTE_MAX) {
            throw new ServiceException('Keep the note under ' . self::NOTE_MAX . ' characters.');
        }

        return $this->transaction(function () use ($requestId, $runnerId, $itemCents, $receipt, $note): int {
            $request = $this->lockOpenRequest($requestId);
            $acceptance = $this->lockAcceptance($requestId);

            if ($acceptance === null || (int) $acceptance['runner_id'] !== $runnerId) {
                throw new ServiceException('Only the runner of this errand can submit its price.');
            }

            // Supersede whatever is still in play (pending or approved-but-unpaid).
            $this->db->table('price_submissions')
                ->where('request_id', $requestId)
                ->whereIn('status', ['pending', 'approved'])
                ->update(['status' => 'superseded']);

            $this->db->table('price_submissions')->insert([
                'request_id'   => $requestId,
                'runner_id'    => $runnerId,
                'item_price'   => Money::toDecimal($itemCents),
                'receipt_path' => $receipt['path'],
                'receipt_name' => mb_substr($receipt['name'], 0, 255),
                'receipt_type' => mb_substr($receipt['type'], 0, 100),
                'note'         => $note !== '' ? $note : null,
                'status'       => 'pending',
                'created_at'   => date('Y-m-d H:i:s'),
            ]);
            $submissionId = (int) $this->db->insertID();

            $wasApproved = $request['price_status'] === 'approved';

            $this->db->table('requests')->where('id', $requestId)->update([
                'price_status'           => 'submitted',
                'approved_item_price'    => null,
                'approved_submission_id' => null,
                // Back to the estimate until a new price is approved.
                'total_amount'           => Money::toDecimal(FeePolicy::requesterTotalCents(Money::toCents($request['estimated_price']), Money::toCents($request['service_fee']))),
                'updated_at'             => date('Y-m-d H:i:s'),
            ]);

            $this->notify(
                (int) $request['user_id'],
                $runnerId,
                $requestId,
                'price_submitted',
                $wasApproved ? 'The runner revised the price' : 'Check the actual price',
                'Your runner says ' . $request['item_name'] . ' cost ' . Money::format($itemCents)
                    . ' (estimated ' . Money::formatDecimal($request['estimated_price']) . '). Check the receipt, then approve or reject it.'
                    . ($wasApproved ? ' Your earlier approval no longer applies.' : '')
            );

            return $submissionId;
        });
    }

    public function approve(int $requestId, int $requesterId, int $submissionId): void
    {
        $this->transaction(function () use ($requestId, $requesterId, $submissionId): void {
            $request    = $this->lockOpenRequest($requestId);
            $submission = $this->latestPendingFor($request, $requesterId, $submissionId);

            $itemCents  = Money::toCents($submission['item_price']);
            $totalCents = FeePolicy::requesterTotalCents($itemCents, Money::toCents($request['service_fee']));

            $this->db->table('price_submissions')->where('id', $submissionId)->update([
                'status'     => 'approved',
                'decided_by' => $requesterId,
                'decided_at' => date('Y-m-d H:i:s'),
            ]);

            $this->db->table('requests')->where('id', $requestId)->update([
                'price_status'           => 'approved',
                'approved_item_price'    => Money::toDecimal($itemCents),
                'approved_submission_id' => $submissionId,
                'total_amount'           => Money::toDecimal($totalCents),
                'updated_at'             => date('Y-m-d H:i:s'),
            ]);

            $this->notify(
                (int) $submission['runner_id'],
                $requesterId,
                $requestId,
                'price_approved',
                'Price approved',
                'The requester approved ' . Money::format($itemCents) . ' for ' . $request['item_name']
                    . '. The total is now ' . Money::format($totalCents) . ' with the service fee.'
            );
        });
    }

    public function reject(int $requestId, int $requesterId, int $submissionId, string $reason): void
    {
        $reason = trim(preg_replace('/\s+/', ' ', $reason) ?? '');

        if (mb_strlen($reason) < self::REASON_MIN || mb_strlen($reason) > self::REASON_MAX) {
            throw new ServiceException('Tell the runner why (' . self::REASON_MIN . ' to ' . self::REASON_MAX . ' characters).');
        }

        $this->transaction(function () use ($requestId, $requesterId, $submissionId, $reason): void {
            $request    = $this->lockOpenRequest($requestId);
            $submission = $this->latestPendingFor($request, $requesterId, $submissionId);

            $this->db->table('price_submissions')->where('id', $submissionId)->update([
                'status'          => 'rejected',
                'decision_reason' => $reason,
                'decided_by'      => $requesterId,
                'decided_at'      => date('Y-m-d H:i:s'),
            ]);

            $this->db->table('requests')->where('id', $requestId)->update([
                'price_status' => 'rejected',
                'updated_at'   => date('Y-m-d H:i:s'),
            ]);

            $this->notify(
                (int) $submission['runner_id'],
                $requesterId,
                $requestId,
                'price_rejected',
                'Price not approved',
                'The requester did not approve ' . Money::formatDecimal($submission['item_price']) . ' for ' . $request['item_name']
                    . ': "' . $reason . '". Submit a corrected price and receipt, or message them.'
            );
        });
    }

    /** @return list<array<string, mixed>> every submission for the request, newest first */
    public function history(int $requestId): array
    {
        return $this->db->table('price_submissions')
            ->where('request_id', $requestId)
            ->orderBy('id', 'DESC')
            ->get()->getResultArray();
    }

    /** A request whose price may still change: assigned, in progress, unpaid. */
    private function lockOpenRequest(int $requestId): array
    {
        $request = $this->lockRow('requests', $requestId);

        if ($request === null) {
            throw new ServiceException('Request not found.');
        }

        if ($request['price_status'] === 'not_required') {
            throw new ServiceException('This request was settled before price approval existed; its price can no longer change.');
        }

        if (in_array($request['payment_status'], ['paid', 'paid_cod'], true)) {
            throw new ServiceException('This request is already paid. Its price is final.');
        }

        if (! in_array($request['status'], ['accepted', 'on_the_way'], true)) {
            throw new ServiceException($request['status'] === 'pending'
                ? 'A runner must accept the request first.'
                : 'This request is ' . str_replace('_', ' ', $request['status']) . '; its price can no longer change.');
        }

        return $request;
    }

    /** The submission being decided must be the request's latest one, still pending, and the decider its requester. */
    private function latestPendingFor(array $request, int $requesterId, int $submissionId): array
    {
        if ((int) $request['user_id'] !== $requesterId) {
            throw new ServiceException('Only the requester can approve or reject the price.');
        }

        $latest = $this->db->query(
            'SELECT * FROM `price_submissions` WHERE `request_id` = ? ORDER BY `id` DESC LIMIT 1 FOR UPDATE',
            [(int) $request['id']],
        )->getRowArray();

        if ($latest === null || (int) $latest['id'] !== $submissionId) {
            throw new ServiceException('The runner has changed the price since you opened this page. Check the new amount and receipt.');
        }

        if ($latest['status'] !== 'pending') {
            throw new ServiceException('You already ' . ($latest['status'] === 'approved' ? 'approved' : 'answered') . ' this price.');
        }

        return $latest;
    }
}

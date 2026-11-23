<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

/**
 * Who may message whom (decision of 2026-11-23, R4). Every chat endpoint
 * asks this class; hiding a button in a view is never the only check.
 *
 *  - New messages: only between a requester and the runner assigned to one
 *    of their errands that is accepted, on the way or delivered. Pending or
 *    cancelled errands don't open a chat.
 *  - A block (either direction) stops new messages. It doesn't delete
 *    history and doesn't change any errand, payment or delivery duty.
 *  - Reading: people always keep their own message history (every query
 *    is limited to messages they sent or received).
 */
class ChatPolicy
{
    public const CHAT_STATUSES   = ['accepted', 'on_the_way', 'delivered'];
    public const ACTIVE_STATUSES = ['accepted', 'on_the_way'];

    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    /**
     * Errands between the two users in which one is the requester and the
     * other the assigned runner, newest first.
     *
     * @param list<string> $statuses
     *
     * @return list<array{id: int|string, item_name: string, status: string, requester_id: int|string, runner_id: int|string}>
     */
    public function sharedErrands(int $a, int $b, array $statuses = self::CHAT_STATUSES): array
    {
        if ($a <= 0 || $b <= 0 || $a === $b) {
            return [];
        }

        return $this->db->table('requests r')
            ->select('r.id, r.item_name, r.status, r.user_id AS requester_id, ar.runner_id')
            ->join('accepted_requests ar', 'ar.request_id = r.id')
            ->groupStart()
                ->groupStart()->where('r.user_id', $a)->where('ar.runner_id', $b)->groupEnd()
                ->orGroupStart()->where('r.user_id', $b)->where('ar.runner_id', $a)->groupEnd()
            ->groupEnd()
            ->whereIn('r.status', $statuses)
            ->orderBy('r.updated_at', 'DESC')->orderBy('r.id', 'DESC')
            ->get()->getResultArray();
    }

    public function hasBlocked(int $blocker, int $blocked): bool
    {
        return $this->db->table('user_blocks')->where('blocker_id', $blocker)->where('blocked_id', $blocked)->countAllResults() > 0;
    }

    public function isBlockedEitherWay(int $a, int $b): bool
    {
        return $this->db->table('user_blocks')
            ->groupStart()
                ->groupStart()->where('blocker_id', $a)->where('blocked_id', $b)->groupEnd()
                ->orGroupStart()->where('blocker_id', $b)->where('blocked_id', $a)->groupEnd()
            ->groupEnd()
            ->countAllResults() > 0;
    }

    /**
     * Whether $sender may send a new message to $receiver now, about
     * $requestId (or about any qualifying shared errand when null).
     *
     * @return array{allowed: bool, reason: string, request_id: int|null}
     */
    public function canSend(int $sender, int $receiver, ?int $requestId = null): array
    {
        $deny = static fn (string $reason): array => ['allowed' => false, 'reason' => $reason, 'request_id' => null];

        if ($sender <= 0 || $receiver <= 0 || $sender === $receiver) {
            return $deny('You cannot message yourself.');
        }

        if ($this->db->table('users')->where('id', $receiver)->countAllResults() === 0) {
            return $deny('That user could not be found.');
        }

        if ($this->isBlockedEitherWay($sender, $receiver)) {
            return $deny($this->hasBlocked($sender, $receiver)
                ? 'You blocked this person, so you can’t send new messages. Unblock them from their profile to chat again.'
                : 'You can’t send new messages to this person. Your errands and earlier messages are not affected.');
        }

        $errands = $this->sharedErrands($sender, $receiver);

        if ($requestId !== null) {
            foreach ($errands as $errand) {
                if ((int) $errand['id'] === $requestId) {
                    return ['allowed' => true, 'reason' => '', 'request_id' => $requestId];
                }
            }

            return $deny('You can only message the requester or runner of that request, once it has been accepted.');
        }

        if ($errands === []) {
            return $deny('You can message someone once one of you accepts the other’s request.');
        }

        return ['allowed' => true, 'reason' => '', 'request_id' => (int) $errands[0]['id']];
    }

    /** Active (accepted / on the way) errands the two share: they need a support route if blocked. */
    public function activeErrands(int $a, int $b): array
    {
        return $this->sharedErrands($a, $b, self::ACTIVE_STATUSES);
    }
}

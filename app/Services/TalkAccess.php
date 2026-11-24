<?php

namespace App\Services;

use Throwable;

/**
 * Keeps TalkJS in line with ChatPolicy. With TalkJS the browser sends
 * messages straight to TalkJS, so a conversation must be made read-only
 * inside TalkJS when the chat closes (block, cancelled errand) and
 * writable again when it reopens (unblock). History is never deleted.
 *
 * Requires "browser synchronization" to be disabled in the TalkJS
 * dashboard; otherwise a browser could change participants itself.
 */
class TalkAccess
{
    public function __construct(private readonly ChatPolicy $policy = new ChatPolicy())
    {
    }

    public static function enabled(): bool
    {
        return config('TalkJS')->isConfigured();
    }

    /** Cache flag meaning "this conversation was set up as writable recently". */
    public static function cacheKey(string $conversationId): string
    {
        return 'talk-rw-' . md5($conversationId);
    }

    /** Re-applies Read / ReadWrite on every TalkJS conversation the two users share. */
    public function syncPair(int $a, int $b): void
    {
        if (! self::enabled()) {
            return;
        }

        $client  = service('talkjs');
        $errands = $this->policy->sharedErrands($a, $b, ['accepted', 'on_the_way', 'delivered', 'cancelled']);

        foreach ($errands as $errand) {
            $requestId = (int) $errand['id'];
            $allowed   = $this->policy->canSend($a, $b, $requestId)['allowed'];

            $conversationId = $client->conversationId($a, $b, $requestId);
            cache()->delete(self::cacheKey($conversationId));

            try {
                $client->setAccess($conversationId, [$a, $b], $allowed ? 'ReadWrite' : 'Read');
            } catch (Throwable $e) {
                // Never block the user's action on the chat provider; the
                // next chat page visit re-applies the same rule.
                log_message('error', 'TalkJS access sync failed for request {r}: {m}', ['r' => $requestId, 'm' => $e->getMessage()]);
            }
        }
    }

    /** Same, for the requester and runner of one request (e.g. after a cancellation). */
    public function syncRequest(int $requestId): void
    {
        if (! self::enabled()) {
            return;
        }

        $row = db_connect()->table('requests r')
            ->select('r.user_id, ar.runner_id')
            ->join('accepted_requests ar', 'ar.request_id = r.id')
            ->where('r.id', $requestId)
            ->get()->getRowArray();

        if ($row !== null) {
            $this->syncPair((int) $row['user_id'], (int) $row['runner_id']);
        }
    }
}

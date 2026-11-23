<?php

namespace App\Services;

use Throwable;

/**
 * Suspends and restores user accounts (admin only; the controller is behind
 * AdminFilter).
 *
 * Rules:
 *  - administrators cannot be suspended here, and nobody can suspend themself;
 *  - a reason is required (3-255 characters) and kept with the account;
 *  - nothing is deleted. Optionally the user's *pending* requests (no runner
 *    yet, unpaid) are cancelled through RequestLifecycle, which keeps their
 *    history; errands already in progress are left for the admin to handle;
 *  - the effects (no login, session ended, chats read-only, requests hidden
 *    from runners) come from AuthFilter, ChatPolicy and Request::browse,
 *    which all read users.status.
 */
class AccountSuspension
{
    /** @return array{cancelled: int, skipped: int} */
    public function suspend(int $userId, int $adminId, string $reason, bool $cancelPending): array
    {
        $reason = trim(preg_replace('/\s+/', ' ', $reason) ?? '');
        $user   = $this->user($userId);

        if ($userId === $adminId) {
            throw new ServiceException('You cannot suspend your own account.');
        }

        if ($user['role'] === 'admin') {
            throw new ServiceException('Administrator accounts cannot be suspended here.');
        }

        if ($user['status'] === 'suspended') {
            throw new ServiceException('This account is already suspended.');
        }

        if (mb_strlen($reason) < 3 || mb_strlen($reason) > 255) {
            throw new ServiceException('Give a reason for the suspension (3 to 255 characters).');
        }

        db_connect()->table('users')->where('id', $userId)->update([
            'status'            => 'suspended',
            'suspended_at'      => date('Y-m-d H:i:s'),
            'suspension_reason' => $reason,
            'suspended_by'      => $adminId,
        ]);

        $result = ['cancelled' => 0, 'skipped' => 0];

        if ($cancelPending) {
            $lifecycle = new RequestLifecycle();

            foreach ($this->pendingRequestIds($userId) as $requestId) {
                try {
                    $lifecycle->cancel($requestId, $adminId, true);
                    $result['cancelled']++;
                } catch (ServiceException) {
                    $result['skipped']++; // changed state meanwhile (e.g. just accepted)
                }
            }
        }

        $this->syncChats($userId);

        return $result;
    }

    public function restore(int $userId): void
    {
        $user = $this->user($userId);

        if ($user['status'] !== 'suspended') {
            throw new ServiceException('This account is not suspended.');
        }

        db_connect()->table('users')->where('id', $userId)->update([
            'status'            => 'active',
            'suspended_at'      => null,
            'suspension_reason' => null,
            'suspended_by'      => null,
        ]);

        $this->syncChats($userId);
    }

    /** @return list<int> pending, unpaid requests posted by the user */
    public function pendingRequestIds(int $userId): array
    {
        return array_map('intval', array_column(db_connect()->table('requests')
            ->select('id')
            ->where('user_id', $userId)
            ->where('status', 'pending')
            ->whereIn('payment_status', ['unpaid', 'cod_pending'])
            ->get()->getResultArray(), 'id'));
    }

    /** Errands in progress (accepted / on the way) where the user is requester or runner. */
    public function activeErrandCount(int $userId): int
    {
        return db_connect()->table('requests r')
            ->join('accepted_requests ar', 'ar.request_id = r.id', 'left')
            ->whereIn('r.status', ['accepted', 'on_the_way'])
            ->groupStart()->where('r.user_id', $userId)->orWhere('ar.runner_id', $userId)->groupEnd()
            ->countAllResults();
    }

    /** @return array<string, mixed> */
    private function user(int $userId): array
    {
        $user = db_connect()->table('users')->select('id, role, status')->where('id', $userId)->get()->getRowArray();

        if ($user === null) {
            throw new ServiceException('User not found.');
        }

        return $user;
    }

    /** Re-apply TalkJS read/write access for every person the user shares an errand with. */
    private function syncChats(int $userId): void
    {
        if (! TalkAccess::enabled()) {
            return;
        }

        $rows = db_connect()->table('requests r')
            ->select('r.user_id, ar.runner_id')
            ->join('accepted_requests ar', 'ar.request_id = r.id')
            ->groupStart()->where('r.user_id', $userId)->orWhere('ar.runner_id', $userId)->groupEnd()
            ->get()->getResultArray();

        $others = [];

        foreach ($rows as $row) {
            $other          = (int) $row['user_id'] === $userId ? (int) $row['runner_id'] : (int) $row['user_id'];
            $others[$other] = true;
        }

        $access = new TalkAccess();

        foreach (array_keys($others) as $other) {
            try {
                $access->syncPair($userId, $other);
            } catch (Throwable $e) {
                log_message('error', 'TalkJS sync after suspension change failed: {m}', ['m' => $e->getMessage()]);
            }
        }
    }
}

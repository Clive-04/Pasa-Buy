<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

/**
 * Public profile numbers (R3). Only facts with a clear definition are
 * shown; no completion or reliability percentages (their denominator and
 * cancellation rules are not defined yet).
 *
 *  - deliveries completed: requests with status delivered where the user
 *    was the assigned runner;
 *  - requests posted: requests the user created (any status);
 *  - ratings: every row in user_ratings about the user (one per delivery,
 *    plus legacy pair ratings), average to one decimal and 5→1 counts.
 *
 * Public reviews show stars, date, the reviewer's first name, the role the
 * reviewer had and their text. Never item names, addresses, contact
 * details, prices or receipts: those can reveal private purchases.
 */
class ProfileStats
{
    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    /** @return array{deliveries: int, requests: int, average: float|null, count: int, distribution: array<int, int>} */
    public function summary(int $userId): array
    {
        $deliveries = $this->db->table('accepted_requests ar')
            ->join('requests r', 'r.id = ar.request_id')
            ->where('ar.runner_id', $userId)->where('r.status', 'delivered')
            ->countAllResults();

        $requests = $this->db->table('requests')->where('user_id', $userId)->countAllResults();

        $distribution = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];

        foreach ($this->db->table('user_ratings')->select('rating, COUNT(*) AS n')->where('rated_user_id', $userId)->groupBy('rating')->get()->getResultArray() as $row) {
            $stars = (int) $row['rating'];
            if (isset($distribution[$stars])) {
                $distribution[$stars] = (int) $row['n'];
            }
        }

        $count = array_sum($distribution);
        $sum   = 0;

        foreach ($distribution as $stars => $n) {
            $sum += $stars * $n;
        }

        return [
            'deliveries'   => $deliveries,
            'requests'     => $requests,
            'average'      => $count > 0 ? round($sum / $count, 1) : null,
            'count'        => $count,
            'distribution' => $distribution,
        ];
    }

    /**
     * One page of reviews about the user, newest first.
     *
     * @return array{items: list<array{rating: int, feedback: string, created_at: string, reviewer: string, reviewer_role: string|null}>, page: int, pages: int, total: int}
     */
    public function reviews(int $userId, int $page = 1, int $perPage = 5): array
    {
        $total = $this->db->table('user_ratings')->where('rated_user_id', $userId)->countAllResults();
        $pages = max(1, (int) ceil($total / $perPage));
        $page  = min(max(1, $page), $pages);

        $rows = $this->db->table('user_ratings ur')
            ->select('ur.rating, ur.feedback, ur.created_at, u.first_name AS reviewer,
                      CASE WHEN r.id IS NULL THEN NULL WHEN r.user_id = ur.rater_id THEN \'requester\' ELSE \'runner\' END AS reviewer_role', false)
            ->join('users u', 'u.id = ur.rater_id')
            ->join('requests r', 'r.id = ur.request_id', 'left')
            ->where('ur.rated_user_id', $userId)
            ->orderBy('ur.created_at', 'DESC')->orderBy('ur.id', 'DESC')
            ->limit($perPage, ($page - 1) * $perPage)
            ->get()->getResultArray();

        return [
            'items' => array_map(static fn (array $r): array => [
                'rating'        => (int) $r['rating'],
                'feedback'      => trim((string) $r['feedback']),
                'created_at'    => (string) $r['created_at'],
                'reviewer'      => (string) $r['reviewer'],
                'reviewer_role' => $r['reviewer_role'],
            ], $rows),
            'page'  => $page,
            'pages' => $pages,
            'total' => $total,
        ];
    }

    /**
     * Errands between the viewer and the profile owner, for the viewer only
     * (the query requires the viewer to be the requester or the runner).
     *
     * @return list<array{id: int, item_name: string, status: string, viewer_role: string, created_at: string}>
     */
    public function sharedErrands(int $viewerId, int $otherId, int $limit = 10): array
    {
        if ($viewerId === $otherId) {
            return [];
        }

        $rows = $this->db->table('requests r')
            ->select("r.id, r.item_name, r.status, r.created_at, IF(r.user_id = {$viewerId}, 'requester', 'runner') AS viewer_role", false)
            ->join('accepted_requests ar', 'ar.request_id = r.id')
            ->groupStart()
                ->groupStart()->where('r.user_id', $viewerId)->where('ar.runner_id', $otherId)->groupEnd()
                ->orGroupStart()->where('r.user_id', $otherId)->where('ar.runner_id', $viewerId)->groupEnd()
            ->groupEnd()
            ->orderBy('r.created_at', 'DESC')->orderBy('r.id', 'DESC')
            ->limit($limit)
            ->get()->getResultArray();

        return array_map(static fn (array $r): array => [
            'id' => (int) $r['id'], 'item_name' => (string) $r['item_name'], 'status' => (string) $r['status'],
            'viewer_role' => (string) $r['viewer_role'], 'created_at' => (string) $r['created_at'],
        ], $rows);
    }
}

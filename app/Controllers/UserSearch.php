<?php

namespace App\Controllers;

use App\Libraries\RateLimiter;
use App\Models\UserModel;
use App\Services\ChatPolicy;
use App\Services\ProfileStats;

class UserSearch extends BaseController
{
    /** Fields of another user that may be shown publicly. */
    private const PUBLIC_FIELDS = 'id, first_name, last_name, username, about_me, profile_photo, created_at';

    private const MIN_QUERY = 2;
    private const PER_PAGE  = 12;

    public function index()
    {
        $keyword = mb_substr(trim((string) $this->request->getGet('q')), 0, 100);
        $page    = max(1, (int) $this->request->getGet('page'));
        $users   = [];
        $total   = 0;
        $notice  = null;

        if ($keyword !== '' && mb_strlen($keyword) < self::MIN_QUERY) {
            $notice = 'Type at least ' . self::MIN_QUERY . ' characters to search.';
        } elseif ($keyword !== '' && ($retry = RateLimiter::hit('search', $this->currentUserId())) !== null) {
            $notice = RateLimiter::message($retry, 'searches');
            $this->response->setStatusCode(429)->setHeader('Retry-After', (string) $retry);
        } elseif ($keyword !== '') {
            $db   = db_connect();
            $like = '%' . $db->escapeLikeString($keyword) . '%';
            $me   = $this->currentUserId();

            // Email/phone/address are private: never searched, never selected.
            $where  = "u.role = 'user' AND u.id <> ? AND (u.first_name LIKE ? ESCAPE '!' OR u.last_name LIKE ? ESCAPE '!'
                       OR CONCAT(u.first_name, ' ', u.last_name) LIKE ? ESCAPE '!' OR u.username LIKE ? ESCAPE '!')";
            $params = [$me, $like, $like, $like, $like];

            $total = (int) $db->query("SELECT COUNT(*) AS n FROM users u WHERE {$where}", $params)->getRow()->n;
            $pages = max(1, (int) ceil($total / self::PER_PAGE));
            $page  = min($page, $pages);

            $users = $db->query(
                "SELECT u.id, u.first_name, u.last_name, u.username, u.about_me, u.profile_photo, u.created_at,
                        (SELECT COUNT(*) FROM accepted_requests ar JOIN requests r ON r.id = ar.request_id
                          WHERE ar.runner_id = u.id AND r.status = 'delivered') AS deliveries,
                        (SELECT COUNT(*) FROM user_ratings ur WHERE ur.rated_user_id = u.id) AS rating_count,
                        (SELECT ROUND(AVG(ur.rating), 1) FROM user_ratings ur WHERE ur.rated_user_id = u.id) AS rating_avg
                 FROM users u
                 WHERE {$where}
                 ORDER BY u.first_name, u.last_name, u.id
                 LIMIT " . self::PER_PAGE . ' OFFSET ' . (($page - 1) * self::PER_PAGE),
                $params,
            )->getResultArray();
        }

        return view('user_search_page', [
            'title'   => 'Search people | Pasa-Buy',
            'keyword' => $keyword,
            'users'   => $users,
            'total'   => $total,
            'page'    => $page,
            'pages'   => max(1, (int) ceil($total / self::PER_PAGE)),
            'perPage' => self::PER_PAGE,
            'notice'  => $notice,
        ]);
    }

    public function view($id)
    {
        $id   = (int) $id;
        $user = (new UserModel())->select(self::PUBLIC_FIELDS . ', role')->find($id);

        if ($user === null || $user['role'] !== 'user') {
            return redirect()->to('/users/search')->with('error', 'User not found.');
        }

        unset($user['role']);

        $me      = $this->currentUserId();
        $isOwn   = $me === $id;
        $stats   = new ProfileStats();
        $policy  = new ChatPolicy();
        $chat    = $isOwn ? null : $policy->canSend($me, $id);

        return view('user_public_profile_page', [
            'title'        => trim($user['first_name'] . ' ' . $user['last_name']) . ' | Pasa-Buy',
            'profileUser'  => $user,
            'summary'      => $stats->summary($id),
            'reviews'      => $stats->reviews($id, (int) $this->request->getGet('reviews') ?: 1),
            'together'     => $isOwn ? [] : $stats->sharedErrands($me, $id),
            'isOwnProfile' => $isOwn,
            'canMessage'   => $chat['allowed'] ?? false,
            'iBlocked'     => ! $isOwn && $policy->hasBlocked($me, $id),
        ]);
    }
}

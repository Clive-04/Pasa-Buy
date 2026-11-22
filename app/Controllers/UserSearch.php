<?php

namespace App\Controllers;

use App\Models\RequestModel;
use App\Models\UserModel;
use App\Models\UserRatingModel;

class UserSearch extends BaseController
{
    /** Fields of another user that may be shown publicly. */
    private const PUBLIC_FIELDS = 'id, first_name, last_name, username, about_me, profile_photo, created_at';

    public function index()
    {
        $keyword = trim((string) $this->request->getGet('q'));
        $users   = [];

        if ($keyword !== '') {
            // Email/phone are private and are neither searched nor shown.
            $users = (new UserModel())
                ->select(self::PUBLIC_FIELDS)
                ->groupStart()
                    ->like('first_name', $keyword)
                    ->orLike('last_name', $keyword)
                    ->orLike('username', $keyword)
                ->groupEnd()
                ->where('id !=', $this->currentUserId())
                ->where('role', 'user')
                ->orderBy('first_name')
                ->findAll(30);
        }

        return view('user_search_page', [
            'title'   => 'Search Users | Pasa-Buy',
            'keyword' => $keyword,
            'users'   => $users,
        ]);
    }

    public function view($id)
    {
        $id   = (int) $id;
        $user = (new UserModel())->select(self::PUBLIC_FIELDS)->find($id);

        if ($user === null) {
            return redirect()->to('/users/search')->with('error', 'User not found.');
        }

        $ratingData = (new UserRatingModel())
            ->select('AVG(rating) AS avg_rating, COUNT(*) AS rating_count')
            ->where('rated_user_id', $id)
            ->first();

        return view('user_public_profile_page', [
            'title'         => 'User Profile | Pasa-Buy',
            'profileUser'   => $user,
            'totalRequests' => (new RequestModel())->where('user_id', $id)->countAllResults(),
            'averageRating' => $ratingData['avg_rating'] !== null ? number_format((float) $ratingData['avg_rating'], 1) : null,
            'ratingCount'   => (int) ($ratingData['rating_count'] ?? 0),
            'isOwnProfile'  => $this->currentUserId() === $id,
        ]);
    }
}

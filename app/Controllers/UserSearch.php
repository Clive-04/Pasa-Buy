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

        $myRating = (new UserRatingModel())
            ->where('rater_id', $this->currentUserId())
            ->where('rated_user_id', $id)
            ->first();

        return view('user_public_profile_page', [
            'title'         => 'User Profile | Pasa-Buy',
            'profileUser'   => $user,
            'totalRequests' => (new RequestModel())->where('user_id', $id)->countAllResults(),
            'averageRating' => $ratingData['avg_rating'] !== null ? number_format((float) $ratingData['avg_rating'], 1) : null,
            'ratingCount'   => (int) ($ratingData['rating_count'] ?? 0),
            'myRating'      => $myRating,
            'canRate'       => $this->haveCompletedErrandTogether($this->currentUserId(), $id),
            'isOwnProfile'  => $this->currentUserId() === $id,
        ]);
    }

    public function rate($id)
    {
        $raterId     = $this->currentUserId();
        $ratedUserId = (int) $id;
        $rating      = (int) $this->request->getPost('rating');
        $feedback    = trim((string) $this->request->getPost('feedback'));
        $back        = redirect()->to('/users/' . $ratedUserId);

        if ($raterId === $ratedUserId) {
            return $back->with('error', 'You cannot rate your own account.');
        }

        if ($rating < 1 || $rating > 5) {
            return $back->withInput()->with('error', 'Please choose a rating from 1 to 5 stars.');
        }

        if (mb_strlen($feedback) > 1000) {
            return $back->withInput()->with('error', 'Feedback must be at most 1000 characters.');
        }

        if ((new UserModel())->select('id')->find($ratedUserId) === null) {
            return redirect()->to('/users/search')->with('error', 'User not found.');
        }

        if (! $this->haveCompletedErrandTogether($raterId, $ratedUserId)) {
            return $back->with('error', 'You can rate someone after completing a delivered request with them.');
        }

        $model    = new UserRatingModel();
        $existing = $model->where('rater_id', $raterId)->where('rated_user_id', $ratedUserId)->first();
        $data     = ['rater_id' => $raterId, 'rated_user_id' => $ratedUserId, 'rating' => $rating, 'feedback' => $feedback ?: null];

        if ($existing !== null) {
            $model->update($existing['id'], $data);

            return $back->with('success', 'Your rating was updated.');
        }

        $model->insert($data);

        return $back->with('success', 'Thank you for your rating.');
    }

    /** True if one was the requester and the other the runner of a delivered request. */
    private function haveCompletedErrandTogether(int $a, int $b): bool
    {
        if ($a === $b) {
            return false;
        }

        return db_connect()->table('accepted_requests ar')
            ->join('requests r', 'r.id = ar.request_id')
            ->where('ar.status', 'delivered')
            ->groupStart()
                ->groupStart()->where('r.user_id', $a)->where('ar.runner_id', $b)->groupEnd()
                ->orGroupStart()->where('r.user_id', $b)->where('ar.runner_id', $a)->groupEnd()
            ->groupEnd()
            ->countAllResults() > 0;
    }
}

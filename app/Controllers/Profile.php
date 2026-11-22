<?php

namespace App\Controllers;

use App\Libraries\UploadStorage;
use App\Models\AcceptedRequestModel;
use App\Models\ReferralModel;
use App\Models\RequestModel;
use App\Models\UserModel;
use App\Models\UserRatingModel;
use App\Models\WebsiteRatingModel;
use App\Services\ServiceException;

class Profile extends BaseController
{
    public function index()
    {
        $userId = $this->currentUserId();
        $user   = (new UserModel())->find($userId);

        if ($user === null) {
            session()->destroy();

            return redirect()->to('/login')->with('error', 'Account not found.');
        }

        $rating = (new UserRatingModel())
            ->select('AVG(rating) AS avg_rating, COUNT(*) AS rating_count')
            ->where('rated_user_id', $userId)
            ->first();

        return view('profile_page', [
            'title'             => 'Profile | Pasa-Buy',
            'user'              => $user,
            'totalRequests'     => (new RequestModel())->where('user_id', $userId)->countAllResults(),
            'acceptedRequests'  => (new AcceptedRequestModel())->where('runner_id', $userId)->countAllResults(),
            'deliveredRequests' => (new AcceptedRequestModel())->where('runner_id', $userId)->where('status', 'delivered')->countAllResults(),
            // Real average from other users' ratings (previously hard-coded 5.0).
            'rating'            => $rating['avg_rating'] !== null ? round((float) $rating['avg_rating'], 1) : null,
            'ratingCount'       => (int) ($rating['rating_count'] ?? 0),
            'myWebsiteRating'   => (new WebsiteRatingModel())->where('user_id', $userId)->first(),
            'isOwnProfile'      => true,
        ]);
    }

    public function updateProfile()
    {
        $userId = $this->currentUserId();

        $input = [
            'first_name' => trim((string) $this->request->getPost('first_name')),
            'last_name'  => trim((string) $this->request->getPost('last_name')),
            'username'   => trim((string) $this->request->getPost('username')),
            'about_me'   => trim((string) $this->request->getPost('about_me')),
        ];

        $validation = service('validation')->setRules([
            'first_name' => ['label' => 'First name', 'rules' => 'required|max_length[20]'],
            'last_name'  => ['label' => 'Last name', 'rules' => 'required|max_length[20]'],
            'username'   => ['label' => 'Username', 'rules' => 'permit_empty|max_length[100]|regex_match[/^[A-Za-z0-9._-]+$/]'],
            'about_me'   => ['label' => 'About me', 'rules' => 'permit_empty|max_length[1000]'],
        ], [
            'username' => ['regex_match' => 'Use letters, numbers, dots, dashes or underscores only.'],
        ]);

        if (! $validation->run($input)) {
            return $this->backWithErrors($validation->getErrors())->with('open_modal', 'editProfileModal');
        }

        $userModel = new UserModel();
        $user      = $userModel->find($userId);
        $photo     = $this->request->getFile('profile_photo');
        $newPhoto  = null;

        if ($photo !== null && $photo->getError() !== UPLOAD_ERR_NO_FILE) {
            try {
                $checked  = UploadStorage::validate($photo, UploadStorage::IMAGE_TYPES, UploadStorage::PROFILE_MAX_BYTES);
                $newPhoto = UploadStorage::store($photo, UploadStorage::PROFILE_PHOTOS, $checked['extension']);
            } catch (ServiceException $e) {
                return $this->backWithErrors(['profile_photo' => $e->getMessage()], $e->getMessage())->with('open_modal', 'editProfileModal');
            }

            $input['profile_photo'] = $newPhoto;
        }

        if (! $userModel->update($userId, $input)) {
            UploadStorage::delete(UploadStorage::PROFILE_PHOTOS, $newPhoto);

            return redirect()->back()->withInput()->with('error', 'Profile update failed.');
        }

        if ($newPhoto !== null) {
            UploadStorage::delete(UploadStorage::PROFILE_PHOTOS, $user['profile_photo'] ?? null);
        }

        session()->set('user_name', trim($input['first_name'] . ' ' . $input['last_name']));

        return redirect()->to('/profile')->with('success', 'Profile updated.');
    }

    public function rateWebsite()
    {
        $userId   = $this->currentUserId();
        $rating   = (int) $this->request->getPost('rating');
        $feedback = trim((string) $this->request->getPost('feedback'));

        if ($rating < 1 || $rating > 5) {
            return redirect()->to('/profile')->withInput()->with('error', 'Please choose a rating from 1 to 5 stars.')->with('open_modal', 'rateWebsiteModal');
        }

        if (mb_strlen($feedback) > 1000) {
            return redirect()->to('/profile')->withInput()->with('error', 'Feedback must be at most 1000 characters.')->with('open_modal', 'rateWebsiteModal');
        }

        $model    = new WebsiteRatingModel();
        $existing = $model->where('user_id', $userId)->first();
        $data     = ['user_id' => $userId, 'rating' => $rating, 'feedback' => $feedback ?: null];

        if ($existing !== null) {
            $model->update($existing['id'], $data);

            return redirect()->to('/profile')->with('success', 'Your website rating was updated.');
        }

        $model->insert($data);

        return redirect()->to('/profile')->with('success', 'Thank you for rating Pasa-Buy.');
    }

    public function referFriend()
    {
        $userId      = $this->currentUserId();
        $friendName  = trim((string) $this->request->getPost('friend_name'));
        $friendEmail = strtolower(trim((string) $this->request->getPost('friend_email')));

        $back = fn (string $message) => redirect()->to('/profile')->withInput()->with('error', $message)->with('open_modal', 'referFriendModal');

        if ($friendName === '' || $friendEmail === '') {
            return $back('Friend name and email are required.');
        }

        if (mb_strlen($friendName) > 100 || mb_strlen($friendEmail) > 150 || ! filter_var($friendEmail, FILTER_VALIDATE_EMAIL)) {
            return $back('Please enter a valid name and email address.');
        }

        $currentUser = (new UserModel())->find($userId);

        if ($currentUser !== null && strtolower($currentUser['email']) === $friendEmail) {
            return $back('You cannot refer your own email.');
        }

        $referrals = new ReferralModel();

        if ($referrals->where('user_id', $userId)->where('friend_email', $friendEmail)->first() !== null) {
            return $back('You already referred this email.');
        }

        $referrals->insert([
            'user_id'      => $userId,
            'friend_name'  => $friendName,
            'friend_email' => $friendEmail,
            'status'       => 'pending',
        ]);

        // No email is sent: the app has no mail delivery configured.
        return redirect()->to('/profile')->with(
            'success',
            'Referral saved for ' . $friendName . '. Pasa-Buy does not send invitation emails, so share the link with them yourself.'
        );
    }
}

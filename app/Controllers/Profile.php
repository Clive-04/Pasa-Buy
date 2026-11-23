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
            'title'             => 'My Profile | Pasa-Buy',
            'user'              => $user,
            'activeRequests'    => (new RequestModel())->where('user_id', $userId)->whereIn('status', ['pending', 'accepted', 'on_the_way'])->countAllResults(),
            'referralCount'     => (new ReferralModel())->where('user_id', $userId)->countAllResults(),
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
            'first_name'   => trim((string) $this->request->getPost('first_name')),
            'last_name'    => trim((string) $this->request->getPost('last_name')),
            'username'     => trim((string) $this->request->getPost('username')),
            'phone_number' => preg_replace('/[\s-]/', '', (string) $this->request->getPost('phone_number')),
            'address'      => trim((string) $this->request->getPost('address')),
            'about_me'     => trim((string) $this->request->getPost('about_me')),
        ];

        // Email is the sign-in identifier and is not editable here.
        $validation = service('validation')->setRules([
            'first_name'   => ['label' => 'First name', 'rules' => 'required|max_length[20]'],
            'last_name'    => ['label' => 'Last name', 'rules' => 'required|max_length[20]'],
            'username'     => ['label' => 'Username', 'rules' => 'permit_empty|max_length[100]|regex_match[/^[A-Za-z0-9._-]+$/]|is_unique[users.username,id,' . $userId . ']'],
            'phone_number' => ['label' => 'Mobile number', 'rules' => 'required|regex_match[/^(\+639|09)\d{9}$/]|is_unique[users.phone_number,id,' . $userId . ']'],
            'address'      => ['label' => 'Address', 'rules' => 'required|max_length[255]'],
            'about_me'     => ['label' => 'About me', 'rules' => 'permit_empty|max_length[1000]'],
        ], [
            'username'     => [
                'regex_match' => 'Use letters, numbers, dots, dashes or underscores only.',
                'is_unique'   => 'That username is taken. Try another one.',
            ],
            'phone_number' => [
                'regex_match' => 'Enter a Philippine mobile number like 09171234567.',
                'is_unique'   => 'Another account already uses this mobile number.',
            ],
        ]);

        if (! $validation->run($input)) {
            return $this->backWithErrors($validation->getErrors())->with('open_modal', 'editProfileModal');
        }

        // No username is stored as NULL, never '', so "no username" can't collide.
        $input['username'] = $input['username'] !== '' ? $input['username'] : null;

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

    public function changePassword()
    {
        $userId  = $this->currentUserId();
        $current = (string) $this->request->getPost('current_password');
        $new     = (string) $this->request->getPost('new_password');
        $confirm = (string) $this->request->getPost('confirm_password');
        $back    = fn (array $errors, string $message) => redirect()->to('/profile')
            ->with('error', $message)->with('errors', $errors)->with('open_modal', 'passwordModal');

        $user = (new UserModel())->find($userId);

        if ($user === null || ! password_verify($current, $user['password'])) {
            return $back(['current_password' => 'Your current password is incorrect.'], 'Your current password is incorrect.');
        }

        $errors = [];

        if (mb_strlen($new) < 8) {
            $errors['new_password'] = 'Use at least 8 characters.';
        } elseif (strlen($new) > 72) {
            $errors['new_password'] = 'Password is too long.';
        } elseif (password_verify($new, $user['password'])) {
            $errors['new_password'] = 'Choose a password different from your current one.';
        }

        if ($new !== $confirm) {
            $errors['confirm_password'] = 'Passwords do not match.';
        }

        if ($errors !== []) {
            return $back($errors, 'Please correct the highlighted fields.');
        }

        (new UserModel())->update($userId, ['password' => password_hash($new, PASSWORD_DEFAULT)]);
        session()->regenerate(true);

        return redirect()->to('/profile')->with('success', 'Password changed. Use your new password next time you log in.');
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

<?php

namespace App\Controllers;

use App\Models\UserModel;

class Auth extends BaseController
{
    /** Failed logins allowed per email+IP before a temporary lockout. */
    private const LOGIN_ATTEMPTS = 5;

    private const LOGIN_WINDOW_SECONDS = 15 * MINUTE;

    public function login()
    {
        if (session('isLoggedIn')) {
            return redirect()->to($this->homeFor((string) session('role')));
        }

        return view('login_page', ['title' => 'Login | Pasa-Buy']);
    }

    public function register()
    {
        if (session('isLoggedIn')) {
            return redirect()->to($this->homeFor((string) session('role')));
        }

        return view('register_page', ['title' => 'Register | Pasa-Buy']);
    }

    public function process()
    {
        $throttler = service('throttler');

        if (! $throttler->check('register-' . md5((string) $this->request->getIPAddress()), 10, HOUR)) {
            return redirect()->back()->with('error', 'Too many sign-up attempts. Please try again later.');
        }

        $rules = [
            'first_name'       => ['label' => 'First name', 'rules' => 'required|max_length[20]'],
            'last_name'        => ['label' => 'Last name', 'rules' => 'required|max_length[20]'],
            'email'            => ['label' => 'Email', 'rules' => 'required|valid_email|max_length[50]|is_unique[users.email]'],
            'phone'            => ['label' => 'Phone number', 'rules' => 'required|regex_match[/^(\+639|09)\d{9}$/]|is_unique[users.phone_number]'],
            'address'          => ['label' => 'Address', 'rules' => 'required|max_length[255]'],
            // Validate the raw password before hashing.
            'password'         => ['label' => 'Password', 'rules' => 'required|min_length[8]|max_length[72]'],
            'confirm_password' => ['label' => 'Confirm password', 'rules' => 'required|matches[password]'],
        ];

        $messages = [
            'email' => ['is_unique' => 'An account with this email already exists.'],
            'phone' => [
                'is_unique'   => 'An account with this phone number already exists.',
                'regex_match' => 'Enter a Philippine mobile number like 09171234567.',
            ],
            'confirm_password' => ['matches' => 'Passwords do not match.'],
        ];

        $input = [
            'first_name'       => trim((string) $this->request->getPost('first_name')),
            'last_name'        => trim((string) $this->request->getPost('last_name')),
            'email'            => strtolower(trim((string) $this->request->getPost('email'))),
            'phone'            => preg_replace('/[\s-]/', '', (string) $this->request->getPost('phone')),
            'address'          => trim((string) $this->request->getPost('address')),
            'password'         => (string) $this->request->getPost('password'),
            'confirm_password' => (string) $this->request->getPost('confirm_password'),
        ];

        $validation = service('validation')->setRules($rules, $messages);
        $valid      = $validation->run($input);
        $errors     = $validation->getErrors();

        // bcrypt ignores everything after 72 bytes; multi-byte characters
        // can exceed that even within 72 characters.
        if (strlen($input['password']) > 72) {
            $errors['password'] = 'Password is too long.';
            $valid              = false;
        }

        if (! $valid) {
            return redirect()->back()
                ->with('error', 'Please correct the highlighted fields.')
                ->with('errors', $errors)
                ->with('form_old', array_diff_key($input, ['password' => 1, 'confirm_password' => 1]));
        }

        $userModel = new UserModel();

        // role is not mass-assignable; the column default ('user') applies.
        // Administrators are provisioned with `php spark user:role`.
        $saved = $userModel->insert([
            'first_name'   => $input['first_name'],
            'last_name'    => $input['last_name'],
            'email'        => $input['email'],
            'phone_number' => $input['phone'],
            'password'     => password_hash($input['password'], PASSWORD_DEFAULT),
            'address'      => $input['address'],
        ]);

        if (! $saved) {
            return redirect()->back()
                ->with('error', 'Registration failed. Please try again.')
                ->with('form_old', array_diff_key($input, ['password' => 1, 'confirm_password' => 1]));
        }

        return redirect()->to('/login')->with('success', 'Registration successful. Please log in.');
    }

    public function loginProcess()
    {
        $email    = strtolower(trim((string) $this->request->getPost('email')));
        $password = (string) $this->request->getPost('password');

        $back = fn (string $message) => redirect()->back()
            ->with('error', $message)
            ->with('form_old', ['email' => $email]);

        if ($email === '' || $password === '') {
            return $back('Enter your email and password.');
        }

        $throttler = service('throttler');
        $key       = 'login-' . md5($email . '|' . $this->request->getIPAddress());

        if (! $throttler->check($key, self::LOGIN_ATTEMPTS, self::LOGIN_WINDOW_SECONDS, 0)) {
            return $back('Too many failed attempts. Please wait ' . max(1, (int) ceil($throttler->getTokenTime() / 60)) . ' minute(s) and try again.');
        }

        $user = (new UserModel())->where('email', $email)->first();

        if ($user === null || ! password_verify($password, $user['password'])) {
            // Spend one token per failure (the check above, cost 0, only peeks).
            $throttler->check($key, self::LOGIN_ATTEMPTS, self::LOGIN_WINDOW_SECONDS, 1);

            // Same message whether the email exists or not.
            return $back('Invalid email or password.');
        }

        // Correct password: forget earlier failures. A suspended account is told
        // so only now, so the message never reveals which emails exist.
        $throttler->remove($key);

        if (($user['status'] ?? 'active') === 'suspended') {
            return $back('This account is suspended. If you think this is a mistake, contact the Pasa-Buy team from the Contact page.');
        }

        // Prevent session fixation.
        session()->regenerate(true);

        if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
            (new UserModel())->update($user['id'], ['password' => password_hash($password, PASSWORD_DEFAULT)]);
        }

        $role = $user['role'] === 'admin' ? 'admin' : 'user';

        session()->set([
            'user_id'    => (int) $user['id'],
            'user_name'  => trim($user['first_name'] . ' ' . $user['last_name']),
            'user_email' => $user['email'],
            'role'       => $role,
            'isLoggedIn' => true,
        ]);

        return redirect()->to($this->homeFor($role))->with('success', 'Welcome back, ' . $user['first_name'] . '!');
    }

    public function logout()
    {
        session()->destroy();

        return redirect()->to('/login')->with('success', 'Logged out successfully.');
    }

    private function homeFor(string $role): string
    {
        return $role === 'admin' ? '/admin/dashboard' : '/dashboard';
    }
}

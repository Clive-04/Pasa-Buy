<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Restricts the `admin` route group to administrators.
 *
 * The role is re-read from the database on every admin request instead of
 * trusting the value cached in the session at login, so demoting an admin
 * takes effect immediately.
 */
class AdminFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $session = session();
        $userId  = (int) $session->get('user_id');

        if (! $session->get('isLoggedIn') || $userId === 0) {
            return redirect()->to('/login')->with('error', 'Please log in first.');
        }

        $user = db_connect()->table('users')->select('role')->where('id', $userId)->get()->getRowArray();

        if ($user === null || $user['role'] !== 'admin') {
            $session->set('role', $user['role'] ?? 'user');

            return redirect()->to('/dashboard')->with('error', 'You do not have access to the admin area.');
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        $response->setHeader('Cache-Control', 'no-store, private');

        return $response;
    }
}

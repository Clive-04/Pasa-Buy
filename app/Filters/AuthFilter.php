<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Requires a signed-in session for every route in the `auth` group, so a
 * forgotten check inside a controller cannot expose a page or action, and
 * ends the session of an account that has been suspended.
 */
class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $session = session();

        if (! $session->get('isLoggedIn') || ! $session->get('user_id')) {
            if (str_contains($request->getHeaderLine('Accept'), 'application/json')) {
                return service('response')->setStatusCode(401)->setJSON(['error' => 'Not signed in.']);
            }

            return redirect()->to('/login')->with('error', 'Please log in first.');
        }

        // Re-read the account on every request: a suspension (or a deleted
        // account) ends the session at once instead of at its next login.
        $user = db_connect()->table('users')->select('status')->where('id', (int) $session->get('user_id'))->get()->getRowArray();

        if ($user === null || $user['status'] === 'suspended') {
            $session->destroy();

            if (str_contains($request->getHeaderLine('Accept'), 'application/json')) {
                return service('response')->setStatusCode(401)->setJSON(['error' => 'This account is not available.']);
            }

            return redirect()->to('/login')->with('error', $user === null
                ? 'Please log in again.'
                : 'This account is suspended. If you think this is a mistake, contact the Pasa-Buy team from the Contact page.');
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Signed-in pages contain personal data; keep them out of shared caches.
        $response->setHeader('Cache-Control', 'no-store, private');

        return $response;
    }
}

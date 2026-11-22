<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Requires a signed-in session for every route in the `auth` group, so a
 * forgotten check inside a controller cannot expose a page or action.
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

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Signed-in pages contain personal data; keep them out of shared caches.
        $response->setHeader('Cache-Control', 'no-store, private');

        return $response;
    }
}

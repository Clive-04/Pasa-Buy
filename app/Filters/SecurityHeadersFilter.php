<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Adds headers that CodeIgniter's SecureHeaders filter does not:
 *  - Strict-Transport-Security on HTTPS responses in production
 *    (CodeIgniter only sends it on its HTTP->HTTPS redirect, where
 *    browsers ignore it);
 *  - Referrer-Policy and Permissions-Policy everywhere.
 */
class SecurityHeadersFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        if (ENVIRONMENT === 'production' && $request instanceof IncomingRequest && $request->isSecure()) {
            $response->setHeader('Strict-Transport-Security', 'max-age=31536000');
        }

        $response->setHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->setHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        return $response;
    }
}

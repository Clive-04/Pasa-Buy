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
 *  - Referrer-Policy and Permissions-Policy everywhere;
 *  - Content-Security-Policy (R6), unless the response already set its own
 *    stricter one (private attachments and receipts use a sandbox policy).
 *
 * The policy allows scripts only from this site and the pinned jsDelivr
 * packages (Turbo, TalkJS core): no inline scripts, no eval. Inline style
 * attributes remain allowed (style-src 'unsafe-inline'); they cannot run code.
 */
class SecurityHeadersFilter implements FilterInterface
{
    /** @return list<string> */
    public static function policy(bool $secure): array
    {
        $policy = [
            "default-src 'self'",
            "script-src 'self' https://cdn.jsdelivr.net",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "font-src 'self' https://fonts.gstatic.com",
            "img-src 'self' data: blob: https:",
            "media-src 'self' blob: https:",
            // TalkJS real-time chat (REST + WebSocket) when enabled.
            "connect-src 'self' https://*.talkjs.com wss://*.talkjs.com",
            "frame-src 'none'",
            "object-src 'none'",
            "base-uri 'self'",
            // Wallet top-ups redirect to the payment provider's hosted checkout.
            "form-action 'self' https://*.xendit.co https://checkout.paymongo.com",
            "frame-ancestors 'self'",
        ];

        if ($secure) {
            $policy[] = 'upgrade-insecure-requests';
        }

        return $policy;
    }

    public function before(RequestInterface $request, $arguments = null)
    {
        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        $secure = $request instanceof IncomingRequest && $request->isSecure();

        if (ENVIRONMENT === 'production' && $secure) {
            $response->setHeader('Strict-Transport-Security', 'max-age=31536000');
        }

        $response->setHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->setHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        $mode = config('PasaBuy')->cspMode;

        if ($mode !== 'off' && ! $response->hasHeader('Content-Security-Policy')) {
            $header = $mode === 'report-only' ? 'Content-Security-Policy-Report-Only' : 'Content-Security-Policy';
            $response->setHeader($header, implode('; ', self::policy($secure)));
        }

        return $response;
    }
}

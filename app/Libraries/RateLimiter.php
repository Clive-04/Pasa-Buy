<?php

namespace App\Libraries;

/**
 * Per-user (or per-IP for guests) limits on actions that write data or call
 * a paid/external API. Built on CodeIgniter's token-bucket Throttler, which
 * keeps its counters in the cache, so limits survive between requests.
 *
 * The limits themselves live in Config\PasaBuy::$rateLimits so they can be
 * tuned per environment from .env. They are demo-scale defaults, not a
 * tested production policy.
 */
final class RateLimiter
{
    /**
     * Spends one token for $action. Returns null when allowed, otherwise the
     * number of seconds to wait before trying again.
     */
    public static function hit(string $action, ?int $userId = null): ?int
    {
        $limits = config('PasaBuy')->rateLimits[$action] ?? null;

        if ($limits === null) {
            return null;
        }

        [$capacity, $seconds] = $limits;
        $throttler = service('throttler');
        $subject   = $userId !== null && $userId > 0 ? 'u' . $userId : 'ip' . service('request')->getIPAddress();

        // Cache keys may not contain reserved characters such as ":" (IPv6).
        if ($throttler->check('rl-' . $action . '-' . md5($subject), (int) $capacity, (int) $seconds)) {
            return null;
        }

        return max(1, (int) $throttler->getTokenTime());
    }

    /** Friendly "please wait" text for a refused action. */
    public static function message(int $retryAfter, string $what = 'attempts'): string
    {
        $wait = $retryAfter >= 60
            ? (int) ceil($retryAfter / 60) . ' minute' . ((int) ceil($retryAfter / 60) === 1 ? '' : 's')
            : $retryAfter . ' second' . ($retryAfter === 1 ? '' : 's');

        return 'Too many ' . $what . ' in a short time. Please wait ' . $wait . ' and try again.';
    }
}

<?php

namespace App\Libraries;

/**
 * Approved fee rule for the first release (decision of 2026-11-22):
 *
 *   platform fee = 10% of the runner's service fee, borne by the runner.
 *
 *   Requester pays : item estimated price + service fee
 *   Runner receives: item estimated price + service fee − platform fee
 *   Platform keeps : platform fee
 *
 * Rounding: half-up to the nearest centavo (₱0.05 fee on ₱0.45 service fee).
 *
 * Collection timing:
 *   - Wallet payment: when the requester pays the runner.
 *   - Cash on delivery: debited from the runner's wallet when the runner
 *     confirms delivery and cash receipt.
 */
final class FeePolicy
{
    public const PLATFORM_FEE_BASIS_POINTS = 1000; // 10.00%

    public const LABEL = '10% of the service fee, paid by the runner';

    public static function platformFeeCents(int $serviceFeeCents): int
    {
        // Integer half-up rounding: (fee × bp + 5000) / 10000.
        return intdiv($serviceFeeCents * self::PLATFORM_FEE_BASIS_POINTS + 5_000, 10_000);
    }

    public static function requesterTotalCents(int $itemCents, int $serviceFeeCents): int
    {
        return $itemCents + $serviceFeeCents;
    }

    public static function runnerNetCents(int $itemCents, int $serviceFeeCents): int
    {
        return $itemCents + $serviceFeeCents - self::platformFeeCents($serviceFeeCents);
    }

    /** Runner's earnings for the errand itself (service fee minus platform fee). */
    public static function runnerServiceEarningsCents(int $serviceFeeCents): int
    {
        return $serviceFeeCents - self::platformFeeCents($serviceFeeCents);
    }
}

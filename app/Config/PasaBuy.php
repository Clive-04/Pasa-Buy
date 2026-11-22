<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Application-specific settings. Each property can be overridden from .env,
 * e.g. `pasabuy.demoTopUpMaxCents = 300000`.
 *
 * The platform-fee rule itself is a business rule, not configuration; it
 * lives in App\Libraries\FeePolicy so that every environment computes the
 * same amounts.
 */
class PasaBuy extends BaseConfig
{
    /**
     * Demo mode: wallet balances are simulated credits, not money.
     * The first release is a portfolio demo (decision of 2026-11-22), and
     * the code has no verified payment integration, so this must stay true
     * until a real funding flow exists.
     */
    public bool $demoMode = true;

    /** Largest single demo top-up, in centavos (₱5,000.00). */
    public int $demoTopUpMaxCents = 500_000;

    /** Highest balance a demo top-up may produce, in centavos (₱20,000.00). */
    public int $demoWalletCapCents = 2_000_000;

    /** Upper bound for an item's estimated price, in centavos (₱100,000.00). */
    public int $maxItemPriceCents = 10_000_000;

    /** Upper bound for a runner's service fee, in centavos (₱10,000.00). */
    public int $maxServiceFeeCents = 1_000_000;

    /** Rows per page on list screens. */
    public int $perPage = 10;

    /** Seconds between background notification-count refreshes (0 = off). */
    public int $notificationPollSeconds = 30;
}

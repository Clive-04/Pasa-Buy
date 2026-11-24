<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * PayMongo (GCash / Maya) settings. Fill these in .env — never commit keys:
 *
 *   paymongo.secretKey     = sk_test_...
 *   paymongo.webhookSecret = whsk_...   (shown when you add the webhook endpoint)
 *
 * When secretKey is empty the app falls back to simulated demo credits.
 * Only TEST keys are accepted: the first release is a demo with no real money.
 */
class PayMongo extends BaseConfig
{
    public string $secretKey = '';
    public string $webhookSecret = '';

    /** The docs show both /v1 and /v2 for checkout sessions; v1 is the documented reference. */
    public string $apiBase = 'https://api.paymongo.com/v1';

    /** @var list<string> PayMongo payment method codes: GCash = gcash, Maya = paymaya */
    public array $methods = ['gcash', 'paymaya'];

    /** Smallest top-up, in centavos (₱100.00). */
    public int $minTopUpCents = 10_000;

    /** Largest single top-up, in centavos (₱5,000.00). */
    public int $maxTopUpCents = 500_000;

    /** Reject webhook signatures older than this many seconds (replay protection). */
    public int $signatureTolerance = 600;

    public function isConfigured(): bool
    {
        return $this->secretKey !== '';
    }

    public function isTestKey(): bool
    {
        return str_starts_with($this->secretKey, 'sk_test_');
    }
}

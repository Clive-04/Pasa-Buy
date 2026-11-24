<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Xendit (GCash / Maya via Payment Sessions). Fill these in .env — never commit them:
 *
 *   xendit.secretKey     = xnd_development_...   (Dashboard → Settings → API keys, TEST mode)
 *   xendit.callbackToken = ...                   (Dashboard → Settings → Webhooks: verification token)
 *
 * Optional, when the site is reached through an HTTPS tunnel (e.g. ngrok):
 *   xendit.publicBaseUrl = https://xxxx.ngrok-free.app/Pasa-Buy-Project/
 * Xendit requires HTTPS return URLs; without one, users come back via the
 * "Check payment" button on the Wallet page.
 */
class Xendit extends BaseConfig
{
    public string $secretKey = '';
    public string $callbackToken = '';
    public string $publicBaseUrl = '';
    public string $apiBase = 'https://api.xendit.co';

    /** @var list<string> Xendit channel codes */
    public array $channels = ['GCASH', 'PAYMAYA'];

    /** Same limits as the rest of the app (₱100 – ₱5,000 per top-up). */
    public int $minTopUpCents = 10_000;
    public int $maxTopUpCents = 500_000;

    public function isConfigured(): bool
    {
        return $this->secretKey !== '';
    }

    /** Xendit live keys start with xnd_production_; this demo refuses them. */
    public function isLiveKey(): bool
    {
        return str_starts_with($this->secretKey, 'xnd_production_');
    }
}

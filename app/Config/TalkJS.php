<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * TalkJS hosted chat. Fill these in .env — never commit the secret key:
 *
 *   talkjs.appId     = tXXXXXXX
 *   talkjs.secretKey = sk_test_...
 *
 * In the TalkJS dashboard (Settings → Security settings):
 *   - enable authentication (identity verification), and
 *   - turn OFF browser synchronization of users and conversations,
 * so only this server can create users, conversations and participants.
 *
 * Pasa-Buy's built-in database chat is the default (decision of 2026-11-23).
 * TalkJS is an optional integration: it is used only when both keys are set
 * AND `pasabuy.talkjsEnabled = true`, so keys alone never switch it on.
 * Before enabling it, confirm the two dashboard settings above.
 */
class TalkJS extends BaseConfig
{
    public string $appId = '';
    public string $secretKey = '';
    public string $apiBase = 'https://api.talkjs.com';

    /** Lifetime of browser user tokens (seconds). TalkJS advises ≥ 10 minutes. */
    public int $userTokenTtl = 86400;

    /** Keys are present (enough for read-only admin tools such as chat:talkjs-inventory). */
    public function hasKeys(): bool
    {
        return $this->appId !== '' && $this->secretKey !== '';
    }

    /** TalkJS is the active chat: keys present and explicitly enabled. */
    public function isConfigured(): bool
    {
        return $this->hasKeys() && config('PasaBuy')->talkjsEnabled;
    }
}

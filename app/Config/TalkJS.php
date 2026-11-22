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
 * When either value is empty, Pasa-Buy uses its built-in database chat.
 */
class TalkJS extends BaseConfig
{
    public string $appId = '';
    public string $secretKey = '';
    public string $apiBase = 'https://api.talkjs.com';

    /** Lifetime of browser user tokens (seconds). TalkJS advises ≥ 10 minutes. */
    public int $userTokenTtl = 86400;

    public function isConfigured(): bool
    {
        return $this->appId !== '' && $this->secretKey !== '';
    }
}

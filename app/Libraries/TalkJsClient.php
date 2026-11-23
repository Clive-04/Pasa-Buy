<?php

namespace App\Libraries;

use App\Services\ServiceException;
use CodeIgniter\HTTP\CURLRequest;
use Config\TalkJS;
use Throwable;

/**
 * Server-side TalkJS helper (docs: https://talkjs.com/docs/).
 *
 * - userToken(): HS256 JWT {tokenType:"user", iss:appId, sub:userId, exp}
 *   given to the browser so TalkJS can verify who the user is.
 * - REST calls use a fresh short-lived admin JWT {tokenType:"admin", iss, exp}.
 * - Users and conversations (with their participants) are created only here,
 *   so a browser can't open or join a conversation it isn't part of
 *   (requires browser synchronization to be disabled in the dashboard).
 */
class TalkJsClient
{
    public function __construct(
        private readonly TalkJS $config,
        private readonly ?CURLRequest $http = null,
    ) {
    }

    public function appId(): string
    {
        return $this->config->appId;
    }

    public function userToken(int $userId, ?int $now = null): string
    {
        $now ??= time();

        return $this->jwt([
            'tokenType' => 'user',
            'iss'       => $this->config->appId,
            'sub'       => self::userId($userId),
            'exp'       => $now + $this->config->userTokenTtl,
        ]);
    }

    public function adminToken(?int $now = null): string
    {
        return $this->jwt([
            'tokenType' => 'admin',
            'iss'       => $this->config->appId,
            'exp'       => ($now ?? time()) + 60,
        ]);
    }

    /** TalkJS user id for a Pasa-Buy user. */
    public static function userId(int $userId): string
    {
        return 'pb_' . $userId;
    }

    /**
     * Stable, unguessable conversation id: one per request thread, or one
     * per pair of users for direct chats.
     */
    public function conversationId(int $userA, int $userB, ?int $requestId = null): string
    {
        $pair = [$userA, $userB];
        sort($pair);
        $key = $requestId !== null ? 'req:' . $requestId . ':' . implode('-', $pair) : 'dm:' . implode('-', $pair);

        return ($requestId !== null ? 'req_' : 'dm_') . substr(hash_hmac('sha256', $key, $this->config->secretKey), 0, 32);
    }

    /** @param array<string, mixed> $user row with id, first_name, last_name, profile_photo */
    public function syncUser(array $user): void
    {
        $body = [
            'name'   => trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: 'Pasa-Buy user',
            'role'   => 'default',
            'custom' => ['pasabuyId' => (string) $user['id']],
        ];

        // Profile photos are private (login required), so no photoUrl is sent;
        // TalkJS shows initials instead.
        $this->request('PUT', '/users/' . rawurlencode(self::userId((int) $user['id'])), $body);
    }

    /** @param list<int> $participants Pasa-Buy user ids */
    public function syncConversation(string $conversationId, array $participants, string $subject, array $custom = []): void
    {
        $this->request('PUT', '/conversations/' . rawurlencode($conversationId), [
            'participants' => array_map(static fn (int $id): string => self::userId($id), $participants),
            'subject'      => mb_substr($subject, 0, 120),
            'custom'       => array_map('strval', $custom),
        ]);
    }

    /** Base64url-encoded HS256 JWT (the only algorithm TalkJS supports). */
    private function jwt(array $claims): string
    {
        $b64 = static fn (string $s): string => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');

        $header  = $b64(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = $b64(json_encode($claims, JSON_UNESCAPED_SLASHES));

        return $header . '.' . $payload . '.' . $b64(hash_hmac('sha256', $header . '.' . $payload, $this->config->secretKey, true));
    }

    /**
     * Read-only summary of the conversations stored in TalkJS, for planning
     * chat-history retention. Returns ids, participant ids, subject and
     * message counts only — never message text.
     *
     * @return list<array{id: string, participants: list<string>, subject: string, messages: int, createdAt: int|null}>
     */
    public function inventory(int $maxConversations = 500): array
    {
        $out   = [];
        $after = null;

        do {
            $page = $this->request('GET', '/conversations?limit=30' . ($after !== null ? '&startingAfter=' . rawurlencode($after) : ''));
            $rows = is_array($page['data'] ?? null) ? $page['data'] : [];

            foreach ($rows as $row) {
                $id = (string) ($row['id'] ?? '');

                if ($id === '') {
                    continue;
                }

                $messages = $this->request('GET', '/conversations/' . rawurlencode($id) . '/messages?limit=100');

                $out[] = [
                    'id'           => $id,
                    'participants' => array_map('strval', array_keys(is_array($row['participants'] ?? null) ? $row['participants'] : [])),
                    'subject'      => (string) ($row['subject'] ?? ''),
                    'messages'     => is_array($messages['data'] ?? null) ? count($messages['data']) : 0,
                    'createdAt'    => isset($row['createdAt']) ? (int) $row['createdAt'] : null,
                ];
                $after = $id;
            }
        } while (count($rows) === 30 && count($out) < $maxConversations);

        return $out;
    }

    /** @return array<string, mixed> decoded JSON body ([] when empty) */
    private function request(string $method, string $path, ?array $json = null): array
    {
        if (! $this->config->hasKeys()) {
            throw new ServiceException('Chat is not configured.');
        }

        $http = $this->http ?? \Config\Services::curlrequest(['timeout' => 15, 'http_errors' => false], null, null, false);

        try {
            $response = $http->request($method, rtrim($this->config->apiBase, '/') . '/v1/' . rawurlencode($this->config->appId) . $path, [
                'headers' => ['Authorization' => 'Bearer ' . $this->adminToken(), 'Accept' => 'application/json'],
            ] + ($json !== null ? ['json' => $json] : []));
        } catch (Throwable $e) {
            log_message('error', 'TalkJS request failed: {m}', ['m' => $e->getMessage()]);

            throw new ServiceException('Chat is temporarily unavailable. Please try again.');
        }

        $status = $response->getStatusCode();

        if ($status < 200 || $status >= 300) {
            log_message('error', 'TalkJS {m} {p} returned {s}: {b}', ['m' => $method, 'p' => $path, 's' => $status, 'b' => ProviderLog::summarize((string) $response->getBody())]);

            throw new ServiceException('Chat is temporarily unavailable. Please try again.');
        }

        $data = json_decode((string) $response->getBody(), true);

        return is_array($data) ? $data : [];
    }
}

<?php

namespace App\Controllers;

use App\Libraries\RateLimiter;
use App\Libraries\UploadStorage;
use App\Models\MessageModel;
use App\Models\RequestModel;
use App\Models\UserModel;
use App\Services\ChatPolicy;
use App\Services\ServiceException;
use App\Services\TalkAccess;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;
use DateTimeImmutable;
use Throwable;

/**
 * Built-in chat (the default). A conversation is everything two people
 * have sent each other; each message also records the errand it was sent
 * about. ChatPolicy decides who may send; every read is limited to
 * messages the viewer sent or received.
 *
 * TalkJS is used instead only when it is explicitly enabled
 * (Config\TalkJS::isConfigured()).
 */
class Message extends BaseController
{
    private const MAX_MESSAGE_LENGTH = 2000;
    private const THREAD_LIMIT       = 200;
    private const POLL_LIMIT         = 50;

    private ChatPolicy $policy;

    private function policy(): ChatPolicy
    {
        return $this->policy ??= new ChatPolicy();
    }

    public function index()
    {
        if (config('TalkJS')->isConfigured()) {
            return $this->talkjsIndex();
        }

        $db        = db_connect();
        $meId      = $this->currentUserId();
        $otherId   = (int) $this->request->getGet('user_id');
        $requestId = (int) $this->request->getGet('request_id') ?: null;
        $search    = mb_substr(trim((string) $this->request->getGet('search')), 0, 100);

        $selectedUser = null;
        $messages     = [];
        $permission   = null;

        if ($otherId > 0 && $otherId !== $meId) {
            $selectedUser = (new UserModel())->select('id, first_name, last_name, profile_photo')->find($otherId);
        }

        if ($selectedUser !== null) {
            // "Deleted for me": only messages after the deletion are shown.
            $messages   = array_reverse($this->pairQuery($meId, $otherId, $this->deletion($meId, $otherId, 0))->orderBy('id', 'DESC')->findAll(self::THREAD_LIMIT));
            $permission = $this->policy()->canSend($meId, $otherId, $requestId);

            // A request_id that doesn't qualify: fall back to the pair's chat.
            if (! $permission['allowed'] && $requestId !== null) {
                $general = $this->policy()->canSend($meId, $otherId);
                if ($general['allowed']) {
                    $permission = $general;
                }
            }

            $this->markThreadRead($meId, $otherId);
        }

        $request = null;
        $contextId = $permission['request_id'] ?? null;

        if ($selectedUser !== null && $contextId !== null) {
            $request = (new RequestModel())->select('id, item_name, status')->find($contextId);
        }

        $blocked = $selectedUser !== null && $this->policy()->hasBlocked($meId, $otherId);

        return view('messages_page', [
            'title'          => 'Messages | Pasa-Buy',
            'selectedUser'   => $selectedUser,
            'messages'       => array_map(fn (array $m): array => $this->payload($m, $meId), $messages),
            'canSend'        => $permission['allowed'] ?? false,
            'cannotReason'   => $permission['reason'] ?? '',
            'requestId'      => $contextId,
            'request'        => $request,
            'iBlocked'       => $blocked,
            'activeErrands'  => $selectedUser !== null ? $this->policy()->activeErrands($meId, $otherId) : [],
            'conversations'  => $this->conversations($meId, $search),
            'errandChats'    => $this->errandChats($meId),
            'search'         => $search,
            'maxLength'      => self::MAX_MESSAGE_LENGTH,
            'pollSeconds'    => (int) config('PasaBuy')->chatPollSeconds,
        ]);
    }

    public function send()
    {
        $senderId   = $this->currentUserId();
        $receiverId = (int) $this->request->getPost('receiver_id');
        $requestId  = (int) $this->request->getPost('request_id') ?: null;
        $message    = trim((string) $this->request->getPost('message'));
        $attachment = $this->request->getFile('attachment');
        $hasFile    = $attachment !== null && $attachment->getError() !== UPLOAD_ERR_NO_FILE;
        $wantsJson  = $this->wantsJson();

        $thread = 'messages?user_id=' . $receiverId . ($requestId !== null ? '&request_id=' . $requestId : '');
        $fail   = function (string $error, int $status = 422) use ($wantsJson, $thread) {
            return $wantsJson
                ? $this->json(['ok' => false, 'error' => $error], $status)
                : redirect()->to($thread)->withInput()->with('error', $error);
        };

        if (($retry = RateLimiter::hit('message-send', $senderId)) !== null) {
            return $fail(RateLimiter::message($retry, 'messages'), 429)->setHeader('Retry-After', (string) $retry);
        }

        $permission = $this->policy()->canSend($senderId, $receiverId, $requestId);

        // The errand named by the form no longer qualifies (e.g. cancelled):
        // the pair may still chat about another errand they share.
        if (! $permission['allowed'] && $requestId !== null) {
            $general    = $this->policy()->canSend($senderId, $receiverId);
            $permission = $general['allowed'] ? $general : $permission;
        }

        if (! $permission['allowed']) {
            return $fail($permission['reason'], 403);
        }

        if ($message === '' && ! $hasFile) {
            return $fail('Type a message or attach a file.');
        }

        if (mb_strlen($message) > self::MAX_MESSAGE_LENGTH) {
            return $fail('Messages can be at most ' . self::MAX_MESSAGE_LENGTH . ' characters.');
        }

        $stored = null;
        $type   = null;

        if ($hasFile) {
            try {
                $checked = UploadStorage::validate($attachment, UploadStorage::IMAGE_TYPES + UploadStorage::DOCUMENT_TYPES, UploadStorage::MESSAGE_MAX_BYTES);
                $stored  = UploadStorage::store($attachment, UploadStorage::MESSAGE_FILES, $checked['extension']);
                $type    = $checked['mime'];
            } catch (ServiceException $e) {
                return $fail($e->getMessage());
            }
        }

        $db = db_connect();

        try {
            $db->resetTransStatus()->transException(true)->transStart();

            $messageId = (new MessageModel())->insert([
                'sender_id'       => $senderId,
                'receiver_id'     => $receiverId,
                'request_id'      => $permission['request_id'],
                'message'         => $message,
                'attachment_name' => $hasFile ? mb_substr(basename((string) $attachment->getClientName()), 0, 255) : null,
                'attachment_path' => $stored !== null ? UploadStorage::MESSAGE_FILES . '/' . $stored : null,
                'attachment_type' => $type,
                'created_at'      => date('Y-m-d H:i:s'),
            ]);

            $db->table('notifications')->insert([
                'user_id'    => $receiverId,
                'sender_id'  => $senderId,
                'message_id' => $messageId,
                'type'       => 'message',
                'is_read'    => 0,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            $db->transComplete();
        } catch (Throwable $e) {
            UploadStorage::delete(UploadStorage::MESSAGE_FILES, $stored);
            log_message('error', 'Message send failed: {message}', ['message' => $e->getMessage()]);

            return $fail('Message could not be sent. Please try again.', 500);
        } finally {
            $db->transException(false);
        }

        if ($wantsJson) {
            $row = (new MessageModel())->find($messageId);

            return $this->json(['ok' => true, 'message' => $this->payload($row, $senderId)]);
        }

        return redirect()->to('messages?user_id=' . $receiverId);
    }

    /**
     * New messages in one conversation after a cursor (message id), for the
     * open chat to poll while the tab is visible. Read-only apart from
     * marking that conversation's message notifications read.
     */
    public function poll()
    {
        $meId    = $this->currentUserId();
        $otherId = (int) $this->request->getGet('user_id');
        $after   = max(0, (int) $this->request->getGet('after'));

        if (($retry = RateLimiter::hit('chat-poll', $meId)) !== null) {
            return $this->json(['error' => 'Too many refreshes.', 'retryAfter' => $retry], 429)->setHeader('Retry-After', (string) $retry);
        }

        if ($otherId <= 0 || $otherId === $meId) {
            return $this->json(['error' => 'Unknown conversation.'], 404);
        }

        $rows = $this->pairQuery($meId, $otherId, $this->deletion($meId, $otherId, 0))->where('id >', $after)->orderBy('id', 'ASC')->findAll(self::POLL_LIMIT);

        if ($rows !== []) {
            $this->markThreadRead($meId, $otherId);
        }

        $permission = $this->policy()->canSend($meId, $otherId);

        return $this->json([
            'messages' => array_map(fn (array $m): array => $this->payload($m, $meId), $rows),
            'more'     => count($rows) === self::POLL_LIMIT,
            'canSend'  => $permission['allowed'],
            'reason'   => $permission['reason'],
            'unread'   => db_connect()->table('notifications')->where('user_id', $meId)->where('type', 'message')->where('is_read', 0)->countAllResults(),
        ]);
    }

    /** Streams an attachment to the sender or receiver only (blocks don't remove access to history). */
    public function attachment($messageId)
    {
        $message = (new MessageModel())->find((int) $messageId);
        $viewer  = $this->currentUserId();

        if ($message === null || empty($message['attachment_path'])
            || (! in_array($viewer, [(int) $message['sender_id'], (int) $message['receiver_id']], true)
                && ! $this->adminReviewingReport((int) $message['id'], $viewer))) {
            throw PageNotFoundException::forPageNotFound();
        }

        $name = basename((string) $message['attachment_path']);
        $path = UploadStorage::path(UploadStorage::MESSAGE_FILES, $name);

        if ($path === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $contentType = UploadStorage::contentType($name);
        $inline      = str_starts_with($contentType, 'image/') || $contentType === 'application/pdf';
        $download    = preg_replace('/[^A-Za-z0-9._ -]/', '_', (string) ($message['attachment_name'] ?: $name));

        return $this->response
            ->setHeader('Content-Type', $contentType)
            ->setHeader('Content-Length', (string) filesize($path))
            ->setHeader('Content-Disposition', ($inline ? 'inline' : 'attachment') . '; filename="' . $download . '"')
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setHeader('Content-Security-Policy', "default-src 'none'; sandbox")
            ->setHeader('Cache-Control', 'no-store, private')
            ->setBody(file_get_contents($path));
    }

    // ------------------------------------------------------------ helpers

    /**
     * Admins may open an attachment only as report evidence: the message must
     * be attached to a user report. The role is re-read from the database,
     * like AdminFilter, so a stale session role is not enough.
     */
    private function adminReviewingReport(int $messageId, int $viewer): bool
    {
        $db = db_connect();

        return $db->table('users')->where('id', $viewer)->where('role', 'admin')->countAllResults() > 0
            && $db->table('user_reports')->where('message_id', $messageId)->countAllResults() > 0;
    }

    /**
     * Messages between exactly these two users; with a "delete for me" row,
     * only the messages after it: by message id when recorded (exact), else
     * by time (rows from before the id was stored).
     *
     * @param array{deleted_at: string, last_message_id: int|string|null}|null $deletion
     */
    private function pairQuery(int $me, int $other, ?array $deletion = null): MessageModel
    {
        $query = (new MessageModel())
            ->groupStart()
                ->groupStart()->where('sender_id', $me)->where('receiver_id', $other)->groupEnd()
                ->orGroupStart()->where('sender_id', $other)->where('receiver_id', $me)->groupEnd()
            ->groupEnd();

        if ($deletion === null) {
            return $query;
        }

        return $deletion['last_message_id'] !== null
            ? $query->where('id >', (int) $deletion['last_message_id'])
            : $query->where('created_at >', $deletion['deleted_at']);
    }

    /** $me's "delete chat" row for $other (request 0 = whole built-in thread), or null. */
    private function deletion(int $me, int $other, int $requestId): ?array
    {
        return db_connect()->table('chat_deletions')
            ->select('deleted_at, last_message_id')
            ->where(['user_id' => $me, 'other_user_id' => $other, 'request_id' => $requestId])
            ->get()->getRowArray();
    }

    /** Milliseconds since the epoch for a DATETIME(3) value (TalkJS timestamps are in ms). */
    private static function toMs(?string $datetime): ?int
    {
        if ($datetime === null) {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('Y-m-d H:i:s.v', $datetime) ?: new DateTimeImmutable($datetime);

        return (int) $date->format('Uv');
    }

    /**
     * "Delete chat" = delete for me. Hides the chat and its messages so far
     * from the current user only. The other person keeps their copy and
     * nothing is erased, so reports keep their evidence. A newer message
     * brings the chat back with just the new messages.
     */
    public function delete()
    {
        $me        = $this->currentUserId();
        $other     = (int) $this->request->getPost('other_user_id');
        $requestId = max(0, (int) $this->request->getPost('request_id'));
        $db        = db_connect();

        if ($other <= 0 || $other === $me || $db->table('users')->where('id', $other)->countAllResults() === 0) {
            return redirect()->to('messages')->with('error', 'That chat could not be found.');
        }

        // Built-in thread: remember the newest message, so a reply arriving in
        // the same second still shows. TalkJS chats compare milliseconds.
        $now    = (new DateTimeImmutable())->format('Y-m-d H:i:s.v');
        $lastId = $requestId === 0
            ? $db->query('SELECT MAX(id) AS last_id FROM messages WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)', [$me, $other, $other, $me])->getRow('last_id')
            : null;
        $db->query(
            'INSERT INTO ' . $db->protectIdentifiers('chat_deletions', true) . ' (user_id, other_user_id, request_id, deleted_at, last_message_id)
             VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE deleted_at = VALUES(deleted_at), last_message_id = VALUES(last_message_id)',
            [$me, $other, $requestId, $now, $lastId !== null ? (int) $lastId : null],
        );

        // Its unread message notices go too, so badges don't point at a deleted chat.
        $this->markThreadRead($me, $other);

        return redirect()->to('messages')->with('success', 'Chat deleted. It’s gone from your list; the other person still has their copy.');
    }

    private function markThreadRead(int $me, int $other): void
    {
        db_connect()->table('notifications')
            ->where('user_id', $me)->where('sender_id', $other)
            ->where('type', 'message')->where('is_read', 0)
            ->update(['is_read' => 1]);
    }

    /**
     * What the browser needs to show one message. Text is escaped by the
     * view / set with textContent by the script, never inserted as HTML.
     *
     * @param array<string, mixed> $m
     *
     * @return array<string, mixed>
     */
    private function payload(array $m, int $viewer): array
    {
        $time = strtotime((string) $m['created_at']);

        return [
            'id'         => (int) $m['id'],
            'mine'       => (int) $m['sender_id'] === $viewer,
            'text'       => (string) $m['message'],
            'request_id' => $m['request_id'] !== null ? (int) $m['request_id'] : null,
            'attachment' => empty($m['attachment_path']) ? null : [
                'url'  => base_url('messages/attachment/' . (int) $m['id']),
                'name' => (string) ($m['attachment_name'] ?: 'Attachment'),
            ],
            'time'       => date('g:i A', $time),
            'day'        => date('Y-m-d', $time) === date('Y-m-d') ? 'Today' : date('F j, Y', $time),
        ];
    }

    /** @return list<array<string, mixed>> latest message per person, newest first */
    private function conversations(int $me, string $search): array
    {
        $db        = db_connect();
        $searchSql = '';
        $params    = array_fill(0, 7, $me);

        if ($search !== '') {
            $searchSql = " AND (u.first_name LIKE ? ESCAPE '!' OR u.last_name LIKE ? ESCAPE '!' OR m1.message LIKE ? ESCAPE '!')";
            $like      = '%' . $db->escapeLikeString($search) . '%';
            array_push($params, $like, $like, $like);
        }

        return $db->query("
            SELECT m1.id, m1.sender_id, m1.request_id, m1.message, m1.attachment_name, m1.created_at,
                   u.id AS user_id, u.first_name, u.last_name, u.profile_photo,
                   (SELECT COUNT(*) FROM notifications n
                     WHERE n.user_id = ? AND n.sender_id = u.id AND n.type = 'message' AND n.is_read = 0) AS unread_count
            FROM messages m1
            INNER JOIN (
                SELECT CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END AS other_user_id,
                       MAX(id) AS latest_id
                FROM messages
                WHERE sender_id = ? OR receiver_id = ?
                GROUP BY other_user_id
            ) latest ON m1.id = latest.latest_id
                    AND ((m1.sender_id = ? AND m1.receiver_id = latest.other_user_id)
                      OR (m1.receiver_id = ? AND m1.sender_id = latest.other_user_id))
            INNER JOIN users u ON u.id = latest.other_user_id
            LEFT JOIN chat_deletions cd ON cd.user_id = ? AND cd.other_user_id = u.id AND cd.request_id = 0
            WHERE (cd.deleted_at IS NULL
                   OR (cd.last_message_id IS NOT NULL AND m1.id > cd.last_message_id)
                   OR (cd.last_message_id IS NULL AND m1.created_at > cd.deleted_at)) {$searchSql}
            ORDER BY m1.id DESC
            LIMIT 200
        ", $params)->getResultArray();
    }

    /**
     * The user's errand chats (one per accepted errand, including finished
     * and cancelled ones so their history stays reachable), with the time
     * the user deleted each one, if they did.
     *
     * @param bool $withClosed include cancelled errands (TalkJS list); the
     *                         built-in list only offers chat-eligible ones
     *
     * @return list<array<string, mixed>>
     */
    private function errandChats(int $userId, bool $withClosed = false, int $limit = 8): array
    {
        $statuses = $withClosed ? "'accepted', 'on_the_way', 'delivered', 'cancelled'" : "'accepted', 'on_the_way', 'delivered'";

        return db_connect()->query(
            "SELECT r.id AS request_id, r.item_name, r.status, r.updated_at, u.id AS other_id, u.first_name, u.last_name, u.profile_photo,
                    IF(r.user_id = ?, 'runner', 'requester') AS other_role, cd.deleted_at
             FROM accepted_requests ar
             JOIN requests r ON r.id = ar.request_id
             JOIN users u ON u.id = IF(r.user_id = ?, ar.runner_id, r.user_id)
             LEFT JOIN chat_deletions cd ON cd.user_id = ? AND cd.other_user_id = u.id AND cd.request_id = r.id
             WHERE (r.user_id = ? OR ar.runner_id = ?)
               AND r.status IN ({$statuses})
             ORDER BY FIELD(r.status, 'on_the_way', 'accepted', 'delivered', 'cancelled'), r.updated_at DESC
             LIMIT " . max(1, $limit),
            [$userId, $userId, $userId, $userId, $userId],
        )->getResultArray();
    }

    private function wantsJson(): bool
    {
        return str_contains($this->request->getHeaderLine('Accept'), 'application/json')
            || strtolower($this->request->getHeaderLine('X-Requested-With')) === 'xmlhttprequest';
    }

    private function json(array $data, int $status = 200): ResponseInterface
    {
        return $this->response->setStatusCode($status)->setHeader('Cache-Control', 'no-store, private')->setJSON($data);
    }

    /**
     * Real-time chat through TalkJS (when enabled), drawn with Pasa-Buy's
     * own markup and CSS: public/js/talk-chat.js reads and sends messages
     * with TalkJS's data API instead of TalkJS's iframe UI.
     *
     * One TalkJS conversation per errand. The server decides who may talk
     * (ChatPolicy), creates the conversation with exactly those two people,
     * and sets them to ReadWrite, or to Read when the chat is closed
     * (blocked / cancelled), so history stays readable but nobody can post.
     */
    private function talkjsIndex()
    {
        $client    = service('talkjs');
        $meId      = $this->currentUserId();
        $otherId   = (int) $this->request->getGet('user_id');
        $requestId = (int) $this->request->getGet('request_id') ?: null;
        $users     = new UserModel();
        $me        = $users->select('id, first_name, last_name, profile_photo')->find($meId);
        $selected  = null;
        $conversationId = null;
        $permission = null;
        $chatError = null;

        if ($otherId > 0 && $otherId !== $meId) {
            $selected = $users->select('id, first_name, last_name, profile_photo')->find($otherId);
        }

        if ($selected !== null) {
            $permission = $this->policy()->canSend($meId, $otherId, $requestId);

            if (! $permission['allowed'] && $requestId !== null) {
                $general    = $this->policy()->canSend($meId, $otherId);
                $permission = $general['allowed'] ? $general : $permission;
            }

            try {
                if ($permission['allowed']) {
                    $requestId      = $permission['request_id'];
                    $conversationId = $client->conversationId($meId, $otherId, $requestId);
                    $setUpKey       = TalkAccess::cacheKey($conversationId);

                    // Each TalkJS REST call takes ~1 s, so a chat that was set up
                    // recently isn't set up again. Blocks, unblocks and
                    // cancellations clear this key (TalkAccess::syncPair).
                    if (cache($setUpKey) === null) {
                        $client->syncUser($me);
                        $client->syncUser($selected);
                        $request = (new RequestModel())->select('id, item_name')->find($requestId);
                        $client->syncConversation(
                            $conversationId,
                            [$meId, $otherId],
                            'Request #' . $request['id'] . ': ' . $request['item_name'],
                            ['requestId' => (string) $request['id']],
                        );
                        $client->setAccess($conversationId, [$meId, $otherId], 'ReadWrite');
                        cache()->save($setUpKey, 1, 600);
                    }
                } else {
                    // Closed chat: show the latest shared errand's history read-only, if any.
                    $shared = $this->policy()->sharedErrands($meId, $otherId, ['accepted', 'on_the_way', 'delivered', 'cancelled']);
                    $match  = array_values(array_filter($shared, static fn (array $e): bool => (int) $e['id'] === $requestId));
                    $errand = $match[0] ?? $shared[0] ?? null;

                    if ($errand !== null) {
                        $requestId      = (int) $errand['id'];
                        $conversationId = $client->conversationId($meId, $otherId, $requestId);

                        if (! $client->setAccess($conversationId, [$meId, $otherId], 'Read')) {
                            $conversationId = null; // never chatted on that errand
                        }
                    } else {
                        $requestId = null;
                    }
                }
            } catch (ServiceException $e) {
                $chatError      = $e->getMessage();
                $conversationId = null;
            }
        }

        $talk = talkjs_boot();

        return view('messages_talkjs_page', [
            'title'          => 'Messages | Pasa-Buy',
            'selectedUser'   => $selected,
            'conversationId' => $conversationId,
            'canSend'        => $chatError === null && ($permission['allowed'] ?? false),
            'cannotReason'   => $permission['reason'] ?? '',
            'chatError'      => $chatError,
            'requestId'      => $requestId,
            'request'        => $requestId !== null ? (new RequestModel())->select('id, item_name, status')->find($requestId) : null,
            'iBlocked'       => $selected !== null && $this->policy()->hasBlocked($meId, $otherId),
            'activeErrands'  => $selected !== null ? $this->policy()->activeErrands($meId, $otherId) : [],
            'errandChats'    => array_map(static function (array $e) use ($client, $meId): array {
                $e['conversation_id'] = $client->conversationId($meId, (int) $e['other_id'], (int) $e['request_id']);
                $e['deleted_ms']      = self::toMs($e['deleted_at']);

                return $e;
            }, $this->errandChats($meId, true, 200)),
            // "Deleted for me": messages up to this moment are hidden in the open chat.
            'deletedMs'      => $selected !== null && $requestId !== null ? self::toMs($this->deletion($meId, $otherId, $requestId)['deleted_at'] ?? null) : null,
            'talk'           => $talk,
            'maxLength'      => self::MAX_MESSAGE_LENGTH,
        ]);
    }
}

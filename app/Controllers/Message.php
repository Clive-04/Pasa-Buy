<?php

namespace App\Controllers;

use App\Libraries\RateLimiter;
use App\Libraries\UploadStorage;
use App\Models\MessageModel;
use App\Models\RequestModel;
use App\Models\UserModel;
use App\Services\ChatPolicy;
use App\Services\ServiceException;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;
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
            $messages   = array_reverse($this->pairQuery($meId, $otherId)->orderBy('id', 'DESC')->findAll(self::THREAD_LIMIT));
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

        $rows = $this->pairQuery($meId, $otherId)->where('id >', $after)->orderBy('id', 'ASC')->findAll(self::POLL_LIMIT);

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

        if ($message === null
            || ! in_array($viewer, [(int) $message['sender_id'], (int) $message['receiver_id']], true)
            || empty($message['attachment_path'])) {
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

    /** Messages between exactly these two users (all errands). */
    private function pairQuery(int $me, int $other): MessageModel
    {
        return (new MessageModel())
            ->groupStart()
                ->groupStart()->where('sender_id', $me)->where('receiver_id', $other)->groupEnd()
                ->orGroupStart()->where('sender_id', $other)->where('receiver_id', $me)->groupEnd()
            ->groupEnd();
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
        $params    = array_fill(0, 6, $me);

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
            WHERE 1 = 1 {$searchSql}
            ORDER BY m1.id DESC
            LIMIT 100
        ", $params)->getResultArray();
    }

    /**
     * People the user can chat with because of an accepted, on-the-way or
     * delivered errand, so a chat can start before any message exists.
     *
     * @return list<array<string, mixed>>
     */
    private function errandChats(int $userId): array
    {
        return db_connect()->query(
            'SELECT r.id AS request_id, r.item_name, r.status, u.id AS other_id, u.first_name, u.last_name, u.profile_photo,
                    IF(r.user_id = ?, \'runner\', \'requester\') AS other_role
             FROM accepted_requests ar
             JOIN requests r ON r.id = ar.request_id
             JOIN users u ON u.id = IF(r.user_id = ?, ar.runner_id, r.user_id)
             WHERE (r.user_id = ? OR ar.runner_id = ?)
               AND r.status IN (\'accepted\', \'on_the_way\', \'delivered\')
             ORDER BY FIELD(r.status, \'on_the_way\', \'accepted\', \'delivered\'), r.updated_at DESC
             LIMIT 8',
            [$userId, $userId, $userId, $userId],
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
     * Hosted chat (TalkJS), only when explicitly enabled. The same
     * ChatPolicy rules decide who may talk; the server creates the
     * conversation with exactly those participants.
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
        $error     = null;

        try {
            $client->syncUser($me);

            if ($otherId > 0 && $otherId !== $meId) {
                $permission = $this->policy()->canSend($meId, $otherId, $requestId);

                if (! $permission['allowed']) {
                    $error = $permission['reason'];
                } else {
                    $selected  = $users->select('id, first_name, last_name, profile_photo')->find($otherId);
                    $requestId = $permission['request_id'];
                    $client->syncUser($selected);
                    $conversationId = $client->conversationId($meId, $otherId, $requestId);
                    $request = (new RequestModel())->select('id, item_name')->find($requestId);
                    $client->syncConversation(
                        $conversationId,
                        [$meId, $otherId],
                        'Request #' . $request['id'] . ': ' . $request['item_name'],
                        ['requestId' => (string) $request['id']],
                    );
                }
            }
        } catch (ServiceException $e) {
            $error = $e->getMessage();
        }

        if ($error !== null) {
            session()->setFlashdata('error', $error);
        }

        return view('messages_talkjs_page', [
            'title'          => 'Messages | Pasa-Buy',
            'conversationId' => $conversationId,
            'selectedUser'   => $selected,
            'requestId'      => $conversationId !== null ? $requestId : null,
            'request'        => $conversationId !== null ? (new RequestModel())->select('id, item_name, status, store_name')->find($requestId) : null,
            'errandChats'    => $this->errandChats($meId),
        ]);
    }
}

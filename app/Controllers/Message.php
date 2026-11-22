<?php

namespace App\Controllers;

use App\Libraries\UploadStorage;
use App\Models\AcceptedRequestModel;
use App\Models\MessageModel;
use App\Models\RequestModel;
use App\Models\UserModel;
use App\Services\ServiceException;
use CodeIgniter\Exceptions\PageNotFoundException;
use Throwable;

class Message extends BaseController
{
    private const MAX_MESSAGE_LENGTH = 2000;
    private const THREAD_LIMIT       = 200;

    public function index()
    {
        if (config('TalkJS')->isConfigured()) {
            return $this->talkjsIndex();
        }

        $db             = db_connect();
        $loggedInUserId = $this->currentUserId();
        $selectedUserId = (int) $this->request->getGet('user_id');
        $requestId      = (int) $this->request->getGet('request_id') ?: null;
        $search         = trim((string) $this->request->getGet('search'));

        $selectedUser = null;
        $messages     = [];

        if ($selectedUserId > 0 && $selectedUserId !== $loggedInUserId) {
            $selectedUser = (new UserModel())->select('id, first_name, last_name, profile_photo')->find($selectedUserId);
        }

        if ($selectedUser !== null) {
            // Opening a conversation marks its message notifications as read.
            $db->table('notifications')
                ->where('user_id', $loggedInUserId)
                ->where('sender_id', $selectedUserId)
                ->where('type', 'message')
                ->where('is_read', 0)
                ->update(['is_read' => 1]);

            $builder = (new MessageModel())
                ->groupStart()
                    ->groupStart()->where('sender_id', $loggedInUserId)->where('receiver_id', $selectedUserId)->groupEnd()
                    ->orGroupStart()->where('sender_id', $selectedUserId)->where('receiver_id', $loggedInUserId)->groupEnd()
                ->groupEnd();

            if ($requestId !== null) {
                $builder->where('request_id', $requestId);
            }

            // Latest messages, shown oldest-first.
            $messages = array_reverse($builder->orderBy('created_at', 'DESC')->orderBy('id', 'DESC')->findAll(self::THREAD_LIMIT));
        }

        $searchSql = '';
        $params    = array_fill(0, 6, $loggedInUserId);

        if ($search !== '') {
            $searchSql = " AND (u.first_name LIKE ? ESCAPE '!' OR u.last_name LIKE ? ESCAPE '!' OR m1.message LIKE ? ESCAPE '!')";
            $like      = '%' . $db->escapeLikeString($search) . '%';
            array_push($params, $like, $like, $like);
        }

        $conversations = $db->query("
            SELECT m1.id, m1.sender_id, m1.receiver_id, m1.request_id, m1.message,
                   m1.attachment_name, m1.created_at,
                   u.id AS user_id, u.first_name, u.last_name,
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

        return view('messages_page', [
            'title'          => 'Messages | Pasa-Buy',
            'selectedUserId' => $selectedUser['id'] ?? null,
            'selectedUser'   => $selectedUser,
            'messages'       => $messages,
            'requestId'      => $requestId,
            'conversations'  => $conversations,
            'search'         => $search,
            'maxLength'      => self::MAX_MESSAGE_LENGTH,
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

        $thread = 'messages?user_id=' . $receiverId . ($requestId !== null ? '&request_id=' . $requestId : '');
        $fail   = fn (string $error) => redirect()->to($thread)->withInput()->with('error', $error);

        if ($receiverId <= 0 || (new UserModel())->select('id')->find($receiverId) === null) {
            return redirect()->to('messages')->with('error', 'That user could not be found.');
        }

        if ($senderId === $receiverId) {
            return redirect()->to('messages')->with('error', 'You cannot message yourself.');
        }

        if ($message === '' && ! $hasFile) {
            return $fail('Type a message or attach a file.');
        }

        if (mb_strlen($message) > self::MAX_MESSAGE_LENGTH) {
            return $fail('Messages can be at most ' . self::MAX_MESSAGE_LENGTH . ' characters.');
        }

        if ($requestId !== null && ! $this->participantsMatch($requestId, $senderId, $receiverId)) {
            return redirect()->to('messages')->with('error', 'You can only message the requester or runner of that request.');
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
                'request_id'      => $requestId,
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

            return $fail('Message could not be sent. Please try again.');
        } finally {
            $db->transException(false);
        }

        return redirect()->to($thread);
    }

    /** Streams an attachment to the sender or receiver only. */
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

    /**
     * Hosted chat (TalkJS). The server decides who may talk to whom, creates
     * the users and the conversation with exactly those participants, and
     * gives the browser a signed token for the current user only.
     */
    private function talkjsIndex()
    {
        $client   = service('talkjs');
        $meId     = $this->currentUserId();
        $otherId  = (int) $this->request->getGet('user_id');
        $requestId = (int) $this->request->getGet('request_id') ?: null;
        $users    = new UserModel();
        $me       = $users->select('id, first_name, last_name, profile_photo')->find($meId);
        $selected = null;
        $conversationId = null;
        $error    = null;

        try {
            $client->syncUser($me);

            if ($otherId > 0 && $otherId !== $meId) {
                $selected = $users->select('id, first_name, last_name, profile_photo')->find($otherId);

                if ($selected === null) {
                    $error = 'That user could not be found.';
                } elseif ($requestId !== null && ! $this->participantsMatch($requestId, $meId, $otherId)) {
                    $error = 'You can only message the requester or runner of that request.';
                    $selected = null;
                } else {
                    $client->syncUser($selected);
                    $conversationId = $client->conversationId($meId, $otherId, $requestId);
                    $request = $requestId !== null ? (new RequestModel())->select('id, item_name')->find($requestId) : null;
                    $client->syncConversation(
                        $conversationId,
                        [$meId, $otherId],
                        $request !== null ? 'Request #' . $request['id'] . ': ' . $request['item_name'] : 'Direct message',
                        $request !== null ? ['requestId' => (string) $request['id']] : [],
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
            'appId'          => $client->appId(),
            'talkUserId'     => \App\Libraries\TalkJsClient::userId($meId),
            'token'          => $client->userToken($meId),
            'conversationId' => $conversationId,
            'selectedUser'   => $selected,
            'requestId'      => $requestId,
        ]);
    }

    /**
     * A message tied to a request must be between its requester and its
     * assigned runner (or, before anyone accepts it, involve the requester).
     */
    private function participantsMatch(int $requestId, int $senderId, int $receiverId): bool
    {
        $request = (new RequestModel())->select('id, user_id')->find($requestId);

        if ($request === null) {
            return false;
        }

        $requesterId = (int) $request['user_id'];
        $acceptance  = (new AcceptedRequestModel())->where('request_id', $requestId)->first();

        if ($acceptance === null) {
            return in_array($requesterId, [$senderId, $receiverId], true);
        }

        $pair = [$requesterId, (int) $acceptance['runner_id']];
        sort($pair);
        $actual = [$senderId, $receiverId];
        sort($actual);

        return $pair === $actual;
    }
}

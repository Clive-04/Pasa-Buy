<?php

namespace App\Controllers;

use App\Services\ChatPolicy;
use App\Services\TalkAccess;

/**
 * Block and Report (R4).
 *
 *  - Block stops new messages both ways. It deletes nothing and doesn't
 *    cancel or change errands, payments or deliveries: an accepted errand
 *    still has to be finished (or cancelled by the usual rules), and the
 *    request page plus "Report" stay available as the support route.
 *  - Report adds an item to the admin moderation queue (admin/reports).
 */
class Moderation extends BaseController
{
    public const REASONS = [
        'harassment'    => 'Harassment or threats',
        'spam'          => 'Spam or unwanted messages',
        'scam'          => 'Scam or payment problem',
        'safety'        => 'Safety concern during an errand',
        'inappropriate' => 'Inappropriate content',
        'other'         => 'Something else',
    ];

    public function block($id)
    {
        $me    = $this->currentUserId();
        $other = (int) $id;
        $back  = $this->backTo($other);

        if (($limited = $this->rateLimited('block', 'block changes', $back, false)) !== null) {
            return $limited;
        }

        if ($other === $me || db_connect()->table('users')->where('id', $other)->countAllResults() === 0) {
            return redirect()->to($back)->with('error', 'That user could not be found.');
        }

        $db = db_connect();

        if (! (new ChatPolicy())->hasBlocked($me, $other)) {
            // INSERT IGNORE: a double-click can't create two rows or an error.
            $db->query('INSERT IGNORE INTO ' . $db->protectIdentifiers('user_blocks', true) . ' (blocker_id, blocked_id, created_at) VALUES (?, ?, ?)', [$me, $other, date('Y-m-d H:i:s')]);
        }

        // TalkJS: make their shared conversations read-only there too.
        (new TalkAccess())->syncPair($me, $other);

        $active = (new ChatPolicy())->activeErrands($me, $other);
        $note   = $active !== []
            ? ' You still share an active errand: finish or cancel it from its request page, and use Report if you need an admin’s help.'
            : '';

        return redirect()->to($back)->with('success', 'Blocked. Neither of you can send new messages. Earlier messages are kept.' . $note);
    }

    public function unblock($id)
    {
        $me    = $this->currentUserId();
        $other = (int) $id;
        $back  = $this->backTo($other);

        if (($limited = $this->rateLimited('block', 'block changes', $back, false)) !== null) {
            return $limited;
        }

        db_connect()->table('user_blocks')->where('blocker_id', $me)->where('blocked_id', $other)->delete();
        (new TalkAccess())->syncPair($me, $other); // reopens only chats ChatPolicy allows

        return redirect()->to($back)->with('success', 'Unblocked. You can message each other again while you share an errand.');
    }

    public function report($id)
    {
        $me        = $this->currentUserId();
        $other     = (int) $id;
        $back      = $this->backTo($other);
        $reason    = (string) $this->request->getPost('reason');
        $details   = trim((string) $this->request->getPost('details'));
        $requestId = (int) $this->request->getPost('request_id') ?: null;
        $messageId = (int) $this->request->getPost('message_id') ?: null;
        $db        = db_connect();

        if ($other === $me || $db->table('users')->where('id', $other)->countAllResults() === 0) {
            return redirect()->to($back)->with('error', 'That user could not be found.');
        }

        if (! array_key_exists($reason, self::REASONS)) {
            return redirect()->to($back)->withInput()->with('error', 'Choose a reason for the report.');
        }

        if (mb_strlen($details) > 1000) {
            return redirect()->to($back)->withInput()->with('error', 'Please keep the details under 1,000 characters.');
        }

        if (($limited = $this->rateLimited('report', 'reports', $back)) !== null) {
            return $limited;
        }

        // Evidence links are kept only if the reporter was really part of them.
        if ($requestId !== null) {
            $shared = (new ChatPolicy())->sharedErrands($me, $other, ['pending', 'accepted', 'on_the_way', 'delivered', 'cancelled']);
            $requestId = in_array($requestId, array_map('intval', array_column($shared, 'id')), true) ? $requestId : null;
        }

        if ($messageId !== null) {
            $isTheirs = $db->table('messages')->where('id', $messageId)
                ->groupStart()
                    ->groupStart()->where('sender_id', $other)->where('receiver_id', $me)->groupEnd()
                    ->orGroupStart()->where('sender_id', $me)->where('receiver_id', $other)->groupEnd()
                ->groupEnd()->countAllResults() > 0;
            $messageId = $isTheirs ? $messageId : null;
        }

        $db->table('user_reports')->insert([
            'reporter_id'      => $me,
            'reported_user_id' => $other,
            'request_id'       => $requestId,
            'message_id'       => $messageId,
            'reason'           => $reason,
            'details'          => $details !== '' ? $details : null,
            'status'           => 'open',
            'created_at'       => date('Y-m-d H:i:s'),
        ]);

        return redirect()->to($back)->with('success', 'Thanks, your report was sent to the Pasa-Buy admins. They will review it. Blocking is available if you don’t want new messages meanwhile.');
    }

    /** Return to the page the action came from when it is on this site, else the profile. */
    private function backTo(int $other): string
    {
        $from = (string) $this->request->getPost('return_to');

        return preg_match('#^(users/\d+|messages(\?user_id=\d+)?|request-details/\d+)$#', $from) === 1 ? $from : 'users/' . $other;
    }
}

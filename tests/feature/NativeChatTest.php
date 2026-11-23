<?php

use App\Libraries\UploadStorage;
use App\Services\RequestLifecycle;
use Tests\Support\FeatureTestCase;

/**
 * Built-in chat authorization, polling and abuse controls (R4).
 * Every rule is checked on the server; tests call the endpoints directly,
 * the way a modified browser could.
 *
 * @internal
 */
final class NativeChatTest extends FeatureTestCase
{
    private RequestLifecycle $lifecycle;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lifecycle = new RequestLifecycle();
    }

    /** @return array{0: int, 1: int, 2: int} requester, runner, request id (accepted) */
    private function errand(string $status = 'accepted'): array
    {
        $requester = $this->createUser(['first_name' => 'Rhea']);
        $runner    = $this->createUser(['first_name' => 'Rolly']);
        $requestId = $this->createRequest($requester, ['item_name' => 'Rice 5kg', 'payment_method' => 'cod']);
        $this->fund($runner, '50.00'); // COD runners hold the platform fee
        $this->lifecycle->accept($requestId, $runner);

        if ($status !== 'accepted') {
            $this->db->table('requests')->where('id', $requestId)->update(['status' => $status]);
            $this->db->table('accepted_requests')->where('request_id', $requestId)->update(['status' => $status]);
        }

        return [$requester, $runner, $requestId];
    }

    private function send(int $from, int $to, string $text, ?int $requestId = null, bool $json = false)
    {
        $data = ['receiver_id' => $to, 'message' => $text] + ($requestId !== null ? ['request_id' => $requestId] : []);

        if ($json) {
            return $this->withHeaders(['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'])->postAs($from, 'messages/send', $data);
        }

        return $this->postAs($from, 'messages/send', $data);
    }

    private function poll(int $viewer, int $other, int $after = 0): array
    {
        $response = $this->getAs($viewer, 'messages/poll?user_id=' . $other . '&after=' . $after);

        return ['status' => $response->response()->getStatusCode(), 'data' => json_decode($response->getJSON() ?: '[]', true)];
    }

    // ---------------------------------------------------------- who may send

    public function testRequesterAndRunnerCanChatOnAcceptedOnTheWayAndDeliveredErrands(): void
    {
        foreach (['accepted', 'on_the_way', 'delivered'] as $status) {
            [$requester, $runner, $requestId] = $this->errand($status);

            $this->send($runner, $requester, 'Hi from ' . $status, $requestId);
            $this->send($requester, $runner, 'Reply ' . $status);
        }

        $this->assertSame(6, $this->countRows('messages'));
        $this->assertSame(6, $this->countRows('notifications', ['type' => 'message']));
    }

    public function testUnrelatedUsersCannotMessageEachOther(): void
    {
        [$requester] = $this->errand();
        $stranger    = $this->createUser();

        $this->send($stranger, $requester, 'Hello?');
        $this->assertStringContainsString('once one of you accepts', (string) $this->flash('error'));

        // Naming someone else's request doesn't help either.
        [, , $otherRequest] = $this->errand();
        $this->send($stranger, $requester, 'Hello?', $otherRequest);

        $this->assertSame(0, $this->countRows('messages'));
    }

    public function testPendingAndCancelledErrandsDoNotOpenAChat(): void
    {
        $requester = $this->createUser();
        $browser   = $this->createUser();
        $pending   = $this->createRequest($requester);

        $this->send($browser, $requester, 'Can you add milk?', $pending);
        $this->assertSame(0, $this->countRows('messages'), 'Pending request: not yet a shared errand.');

        [$r2, $runner, $requestId] = $this->errand('cancelled');
        $this->send($runner, $r2, 'Still there?', $requestId);
        $this->send($runner, $r2, 'Still there?');
        $this->assertSame(0, $this->countRows('messages'), 'Cancelled errand: chat closed for new messages.');
    }

    public function testJsonSendReturnsTheMessageOrAClearError(): void
    {
        [$requester, $runner, $requestId] = $this->errand();

        $ok = $this->send($runner, $requester, '<b>On my way</b>', $requestId, true);
        $ok->assertStatus(200);
        $data = json_decode($ok->getJSON(), true);
        $this->assertTrue($data['ok']);
        $this->assertSame('<b>On my way</b>', $data['message']['text'], 'Stored as text; the browser sets it with textContent.');
        $this->assertTrue($data['message']['mine']);

        $stranger = $this->createUser();
        $denied   = $this->send($stranger, $requester, 'hi', null, true);
        $denied->assertStatus(403);
        $this->assertFalse(json_decode($denied->getJSON(), true)['ok']);

        $empty = $this->send($runner, $requester, '   ', $requestId, true);
        $empty->assertStatus(422);
        $this->assertSame('Type a message or attach a file.', json_decode($empty->getJSON(), true)['error']);
    }

    public function testSendingIsRateLimitedWithRetryAfter(): void
    {
        $saved = config('PasaBuy')->rateLimits;
        config('PasaBuy')->rateLimits['message-send'] = [2, 60];

        try {
            [$requester, $runner, $requestId] = $this->errand();
            $this->send($runner, $requester, 'one', $requestId, true);
            $this->send($runner, $requester, 'two', $requestId, true);
            $third = $this->send($runner, $requester, 'three', $requestId, true);

            $third->assertStatus(429);
            $this->assertNotSame('', $third->response()->getHeaderLine('Retry-After'));
            $this->assertStringStartsWith('Too many messages', json_decode($third->getJSON(), true)['error']);
            $this->assertSame(2, $this->countRows('messages'));
        } finally {
            config('PasaBuy')->rateLimits = $saved;
        }
    }

    // ------------------------------------------------------------- reading

    public function testPollReturnsOnlyNewMessagesOfThatPairAndMarksThemRead(): void
    {
        [$requester, $runner, $requestId] = $this->errand();
        [$otherRequester, $otherRunner, $otherId] = $this->errand();

        $this->send($runner, $requester, 'first', $requestId);
        $this->send($otherRunner, $otherRequester, 'not yours', $otherId);

        $first = $this->poll($requester, $runner);
        $this->assertSame(200, $first['status']);
        $this->assertSame(['first'], array_column($first['data']['messages'], 'text'));
        $this->assertTrue($first['data']['canSend']);
        $this->assertSame(0, $first['data']['unread'], 'Polling an open chat marks it read.');

        $cursor = $first['data']['messages'][0]['id'];
        $this->send($runner, $requester, 'second', $requestId);

        $next = $this->poll($requester, $runner, $cursor);
        $this->assertSame(['second'], array_column($next['data']['messages'], 'text'), 'The cursor prevents duplicates.');
        $this->assertSame([], $this->poll($requester, $runner, $next['data']['messages'][0]['id'])['data']['messages']);
    }

    public function testOutsiderPollingSomeoneElsesConversationGetsNothing(): void
    {
        [$requester, $runner, $requestId] = $this->errand();
        $this->send($runner, $requester, 'private', $requestId);
        $outsider = $this->createUser();

        $result = $this->poll($outsider, $runner);

        $this->assertSame([], $result['data']['messages']);
        $this->assertFalse($result['data']['canSend']);
        $this->getAs($outsider, 'messages?user_id=' . $runner)->assertDontSee('private');
    }

    public function testPollingIsRateLimited(): void
    {
        $saved = config('PasaBuy')->rateLimits;
        config('PasaBuy')->rateLimits['chat-poll'] = [1, 60];

        try {
            [$requester, $runner] = $this->errand();
            $this->assertSame(200, $this->poll($requester, $runner)['status']);
            $second = $this->getAs($requester, 'messages/poll?user_id=' . $runner);
            $second->assertStatus(429);
            $this->assertNotSame('', $second->response()->getHeaderLine('Retry-After'));
        } finally {
            config('PasaBuy')->rateLimits = $saved;
        }
    }

    public function testCancelledErrandKeepsItsHistoryReadable(): void
    {
        [$requester, $runner, $requestId] = $this->errand();
        $this->send($runner, $requester, 'Store is closed', $requestId);
        $this->db->table('requests')->where('id', $requestId)->update(['status' => 'cancelled']);

        $page = $this->getAs($requester, 'messages?user_id=' . $runner);
        $page->assertSee('Store is closed');
        $page->assertSee('data-chat-closed', null);
        $page->assertDontSee('data-chat-composer', null);
        $this->assertFalse($this->poll($requester, $runner)['data']['canSend']);
    }

    // ---------------------------------------------------------- attachments

    public function testAttachmentsAreOnlyServedToTheTwoParticipants(): void
    {
        [$requester, $runner, $requestId] = $this->errand();
        $name = bin2hex(random_bytes(8)) . '.pdf';
        $dir  = WRITEPATH . 'uploads/' . UploadStorage::MESSAGE_FILES;
        is_dir($dir) || mkdir($dir, 0775, true);
        file_put_contents($dir . '/' . $name, "%PDF-1.4\n% receipt\n");

        $this->db->table('messages')->insert([
            'sender_id' => $runner, 'receiver_id' => $requester, 'request_id' => $requestId, 'message' => '',
            'attachment_name' => 'receipt.pdf', 'attachment_path' => UploadStorage::MESSAGE_FILES . '/' . $name,
            'attachment_type' => 'application/pdf', 'created_at' => date('Y-m-d H:i:s'),
        ]);
        $messageId = (int) $this->db->insertID();

        try {
            $this->getAs($requester, 'messages/attachment/' . $messageId)->assertOK();
            $this->getAs($runner, 'messages/attachment/' . $messageId)->assertOK();

            $outsider = $this->createUser();
            $this->assertNotFound(fn () => $this->getAs($outsider, 'messages/attachment/' . $messageId));
            $this->assertRedirectsTo($this->getAs(null, 'messages/attachment/' . $messageId), 'login');

            // Blocking doesn't take away either person's history.
            $this->postAs($requester, 'users/' . $runner . '/block');
            $this->getAs($runner, 'messages/attachment/' . $messageId)->assertOK();
        } finally {
            @unlink($dir . '/' . $name);
        }
    }

    // --------------------------------------------------------------- block

    public function testBlockStopsNewMessagesBothWaysButKeepsHistoryAndTheErrand(): void
    {
        [$requester, $runner, $requestId] = $this->errand();
        $this->send($runner, $requester, 'Buying now', $requestId);

        $this->assertRedirectsTo($this->postAs($requester, 'users/' . $runner . '/block'), 'users/' . $runner);
        $this->assertStringContainsString('You still share an active errand', (string) $this->flash('success'));
        $this->postAs($requester, 'users/' . $runner . '/block'); // double-click is harmless
        $this->assertSame(1, $this->countRows('user_blocks'));

        $this->send($runner, $requester, 'Hello?', $requestId);
        $this->send($requester, $runner, 'Bye', $requestId);
        $this->assertSame(1, $this->countRows('messages'));

        // History stays, the composer is replaced by the support route.
        $page = $this->getAs($runner, 'messages?user_id=' . $requester);
        $page->assertSee('Buying now');
        $page->assertSee('request-details/' . $requestId, null);
        $page->assertDontSee('data-chat-composer', null);

        // The errand itself carries on: the runner can still update it.
        $this->postAs($runner, 'request/status/' . $requestId, ['status' => 'on_the_way']);
        $this->assertSame('on_the_way', $this->requestRow($requestId)['status']);

        // Only the blocker can lift the block.
        $this->postAs($runner, 'users/' . $requester . '/unblock');
        $this->assertSame(1, $this->countRows('user_blocks'));
        $this->postAs($requester, 'users/' . $runner . '/unblock');
        $this->assertSame(0, $this->countRows('user_blocks'));
        $this->send($runner, $requester, 'Back again', $requestId);
        $this->assertSame(2, $this->countRows('messages'));
    }

    public function testCannotBlockYourselfOrUnknownUsers(): void
    {
        $me = $this->createUser();

        $this->postAs($me, 'users/' . $me . '/block');
        $this->postAs($me, 'users/99999/block');

        $this->assertSame(0, $this->countRows('user_blocks'));
    }

    // -------------------------------------------------------------- report

    public function testReportGoesToTheAdminQueueWithOnlyVerifiedEvidence(): void
    {
        [$requester, $runner, $requestId] = $this->errand();
        $this->send($runner, $requester, 'Pay me extra or else', $requestId);
        $messageId = (int) $this->db->table('messages')->select('id')->get()->getRow()->id;

        [, , $unrelatedRequest] = $this->errand();

        $this->postAs($requester, 'users/' . $runner . '/report', [
            'reason' => 'harassment', 'details' => 'Threatening message', 'request_id' => $requestId,
            'message_id' => $messageId, 'return_to' => 'messages?user_id=' . $runner,
        ]);
        $this->postAs($requester, 'users/' . $runner . '/report', ['reason' => 'scam', 'request_id' => $unrelatedRequest, 'message_id' => 424242]);

        $rows = $this->db->table('user_reports')->orderBy('id')->get()->getResultArray();
        $this->assertCount(2, $rows);
        $this->assertSame([$requestId, $messageId], [(int) $rows[0]['request_id'], (int) $rows[0]['message_id']]);
        $this->assertNull($rows[1]['request_id'], 'An errand the reporter is not part of is not linked.');
        $this->assertNull($rows[1]['message_id']);

        $this->postAs($requester, 'users/' . $runner . '/report', ['reason' => 'made-up']);
        $this->assertSame('Choose a reason for the report.', $this->flash('error'));
        $this->assertSame(2, $this->countRows('user_reports'));

        // Admin queue.
        $admin = $this->createUser(['role' => 'admin']);
        $queue = $this->getAs($admin, 'admin/reports', 'admin');
        $queue->assertOK();
        $queue->assertSee('Pay me extra or else');
        $queue->assertSee('Harassment or threats');

        $this->postAs($admin, 'admin/reports/' . $rows[0]['id'], ['status' => 'resolved', 'admin_note' => 'Warned the runner'], 'admin');
        $row = $this->db->table('user_reports')->where('id', $rows[0]['id'])->get()->getRowArray();
        $this->assertSame('resolved', $row['status']);
        $this->assertSame($admin, (int) $row['handled_by']);

        // Ordinary users can't see or change the queue.
        $this->assertRedirectsTo($this->getAs($requester, 'admin/reports'), 'dashboard');
    }

    public function testReportingIsRateLimited(): void
    {
        $saved = config('PasaBuy')->rateLimits;
        config('PasaBuy')->rateLimits['report'] = [1, 3600];

        try {
            $me    = $this->createUser();
            $other = $this->createUser();
            $this->postAs($me, 'users/' . $other . '/report', ['reason' => 'spam']);
            $this->postAs($me, 'users/' . $other . '/report', ['reason' => 'spam']);

            $this->assertSame(1, $this->countRows('user_reports'));
            $this->assertStringStartsWith('Too many reports', (string) $this->flash('error'));
        } finally {
            config('PasaBuy')->rateLimits = $saved;
        }
    }

    // ------------------------------------------------- search and delete

    public function testChatListSearchMatchesNamesAndMessageText(): void
    {
        $requester   = $this->createUser(['first_name' => 'Rhea']);
        $runner      = $this->createUser(['first_name' => 'Rolly']);
        $otherRunner = $this->createUser(['first_name' => 'Zoltan']);
        $requestId   = $this->createRequest($requester);
        $otherRequest = $this->createRequest($requester);
        $this->lifecycle->accept($requestId, $runner);
        $this->lifecycle->accept($otherRequest, $otherRunner);

        $this->send($runner, $requester, 'The pharmacy has generic paracetamol', $requestId);
        $this->send($otherRunner, $requester, 'Bread is sold out', $otherRequest);

        $byText = $this->getAs($requester, 'messages?search=paracetamol');
        $byText->assertSee('The pharmacy has generic paracetamol');
        $byText->assertDontSee('Bread is sold out');

        $byName = $this->getAs($requester, 'messages?search=zolt');
        $byName->assertSee('Bread is sold out');
        $byName->assertDontSee('generic paracetamol');
    }

    public function testDeleteChatHidesItForMeOnlyUntilANewMessage(): void
    {
        [$requester, $runner, $requestId] = $this->errand();
        $this->send($runner, $requester, 'Old message before delete', $requestId);
        $this->send($requester, $runner, 'My old reply', $requestId);

        $this->assertRedirectsTo($this->postAs($requester, 'messages/delete', ['other_user_id' => $runner, 'request_id' => 0]), 'messages');
        $this->postAs($requester, 'messages/delete', ['other_user_id' => $runner, 'request_id' => 0]); // repeat is harmless
        $this->assertSame(1, $this->countRows('chat_deletions'));
        $this->assertSame(2, $this->countRows('messages'), 'Nothing is erased: reports keep their evidence.');

        // Gone for the requester: list, thread and polling.
        $this->getAs($requester, 'messages')->assertDontSee('Old message before delete');
        $this->getAs($requester, 'messages?user_id=' . $runner)->assertDontSee('Old message before delete');
        $this->assertSame([], $this->poll($requester, $runner)['data']['messages']);

        // The runner still has the full chat.
        $this->getAs($runner, 'messages?user_id=' . $requester)->assertSee('My old reply');

        // A new message brings the chat back with only the new message.
        sleep(1); // created_at has one-second precision
        $this->send($runner, $requester, 'Are you still there?', $requestId);
        $list = $this->getAs($requester, 'messages');
        $list->assertSee('Are you still there?');
        $thread = $this->getAs($requester, 'messages?user_id=' . $runner);
        $thread->assertSee('Are you still there?');
        $thread->assertDontSee('Old message before delete');
    }

    public function testDeleteNeedsARealOtherUser(): void
    {
        $me = $this->createUser();

        $this->postAs($me, 'messages/delete', ['other_user_id' => $me]);
        $this->postAs($me, 'messages/delete', ['other_user_id' => 99999]);
        $this->assertSame(0, $this->countRows('chat_deletions'));
        $this->assertNotFound(fn () => $this->getAs($me, 'messages/delete'));
    }

    public function testReturnLinksOnlyGoToKnownPages(): void
    {
        $me    = $this->createUser();
        $other = $this->createUser();

        $this->assertRedirectsTo($this->postAs($me, 'users/' . $other . '/block', ['return_to' => 'https://evil.example/x']), 'users/' . $other);
    }
}

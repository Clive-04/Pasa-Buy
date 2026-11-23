<?php

use App\Libraries\TalkJsClient;
use App\Services\RequestLifecycle;
use CodeIgniter\Config\Services;
use Tests\Support\FeatureTestCase;

/**
 * TalkJS hosted chat. REST calls are captured by a fake client so tests
 * need no network; JWTs are checked against the documented claims.
 *
 * @internal
 */
final class TalkJsMessagingTest extends FeatureTestCase
{
    private object $fake;

    protected function setUp(): void
    {
        parent::setUp();

        config('TalkJS')->appId     = 'tTestApp';
        config('TalkJS')->secretKey = 'sk_test_talkjs_secret';
        config('PasaBuy')->talkjsEnabled = true; // TalkJS must be switched on explicitly

        $this->fake = new class (config('TalkJS')) extends TalkJsClient {
            public array $users = [];
            public array $conversations = [];

            public function syncUser(array $user): void
            {
                $this->users[] = (int) $user['id'];
            }

            public array $access = [];

            public function syncConversation(string $conversationId, array $participants, string $subject, array $custom = []): void
            {
                $this->conversations[$conversationId] = compact('participants', 'subject', 'custom');
            }

            public function setAccess(string $conversationId, array $userIds, string $access): bool
            {
                if (! isset($this->conversations[$conversationId])) {
                    return false; // like TalkJS: 404 for a conversation that was never created
                }
                $this->access[$conversationId] = $access;

                return true;
            }
        };

        Services::injectMock('talkjs', $this->fake);
    }

    protected function tearDown(): void
    {
        config('TalkJS')->appId     = '';
        config('TalkJS')->secretKey = '';
        config('PasaBuy')->talkjsEnabled = false;
        Services::resetSingle('talkjs');

        parent::tearDown();
    }

    private function decode(string $jwt): array
    {
        [$h, $p, $s] = explode('.', $jwt);
        $expected = rtrim(strtr(base64_encode(hash_hmac('sha256', "{$h}.{$p}", 'sk_test_talkjs_secret', true)), '+/', '-_'), '=');
        $this->assertSame($expected, $s, 'Signed with the secret key (HS256).');

        return [json_decode(base64_decode(strtr($h, '-_', '+/')), true), json_decode(base64_decode(strtr($p, '-_', '+/')), true)];
    }

    public function testUserTokenHasDocumentedClaims(): void
    {
        [$header, $claims] = $this->decode($this->fake->userToken(42, 1_000));

        $this->assertSame('HS256', $header['alg']);
        $this->assertSame('user', $claims['tokenType']);
        $this->assertSame('tTestApp', $claims['iss']);
        $this->assertSame('pb_42', $claims['sub']);
        $this->assertSame(1_000 + 86400, $claims['exp']);

        [, $admin] = $this->decode($this->fake->adminToken(1_000));
        $this->assertSame('admin', $admin['tokenType']);
        $this->assertArrayNotHasKey('sub', $admin, 'Admin tokens must not include sub.');
    }

    public function testConversationIdsAreStableAndUnguessable(): void
    {
        $a = $this->fake->conversationId(5, 9, 77);

        $this->assertSame($a, $this->fake->conversationId(9, 5, 77), 'Order of users does not matter.');
        $this->assertNotSame($a, $this->fake->conversationId(5, 9, 78));
        $this->assertNotSame($a, $this->fake->conversationId(5, 9));
        $this->assertStringNotContainsString('77', substr($a, 4));
        $this->assertMatchesRegularExpression('/^req_[a-f0-9]{32}$/', $a);
    }

    public function testMessagesPageCreatesTheRequestConversationAndNeverLeaksTheSecret(): void
    {
        $requester = $this->createUser();
        $runner    = $this->createUser();
        $requestId = $this->createRequest($requester, ['item_name' => 'Cooking oil']);
        (new RequestLifecycle())->accept($requestId, $runner);

        $page = $this->getAs($runner, 'messages?user_id=' . $requester . '&request_id=' . $requestId);

        $page->assertOK();
        $page->assertSee('js/talk-chat.js', null);
        $page->assertSee('"tTestApp"', null);
        $page->assertDontSee('sk_test_talkjs_secret');
        // Site-styled UI: our composer and bubbles, no TalkJS iframe UI.
        $page->assertSee('data-talk-composer', null);
        $page->assertDontSee('createInbox', null);
        $page->assertDontSee('cdn.talkjs.com/talk.js', null);

        $conversationId = $this->fake->conversationId($runner, $requester, $requestId);
        $this->assertArrayHasKey($conversationId, $this->fake->conversations);
        $this->assertSame(['pb_' . $runner, 'pb_' . $requester], array_map(static fn ($id) => 'pb_' . $id, $this->fake->conversations[$conversationId]['participants']));
        $this->assertSame('Request #' . $requestId . ': Cooking oil', $this->fake->conversations[$conversationId]['subject']);
        $this->assertSame('ReadWrite', $this->fake->access[$conversationId]);
        $page->assertSee('data-talk-conversation="' . $conversationId . '"', null);
    }

    public function testClosedChatIsMadeReadOnlyInsideTalkJs(): void
    {
        $requester = $this->createUser();
        $runner    = $this->createUser();
        $requestId = $this->createRequest($requester, ['item_name' => 'Cooking oil']);
        (new RequestLifecycle())->accept($requestId, $runner);
        $conversationId = $this->fake->conversationId($runner, $requester, $requestId);

        $this->getAs($runner, 'messages?user_id=' . $requester . '&request_id=' . $requestId);
        $this->assertSame('ReadWrite', $this->fake->access[$conversationId]);

        // Blocking switches both people to read-only in TalkJS straight away.
        $this->postAs($requester, 'users/' . $runner . '/block');
        $this->assertSame('Read', $this->fake->access[$conversationId]);

        $page = $this->getAs($runner, 'messages?user_id=' . $requester . '&request_id=' . $requestId);
        $page->assertSee('data-talk-conversation="' . $conversationId . '"', null); // history still readable
        $page->assertSee('data-chat-closed', null);
        $page->assertDontSee('data-talk-composer', null);

        // Unblocking reopens it.
        $this->postAs($requester, 'users/' . $runner . '/unblock');
        $this->assertSame('ReadWrite', $this->fake->access[$conversationId]);

        // A cancelled errand closes it again (admin cancellation of an accepted errand).
        $admin = $this->createUser(['role' => 'admin']);
        $this->postAs($admin, 'admin/request/cancel/' . $requestId, [], 'admin');
        $this->assertSame('cancelled', $this->requestRow($requestId)['status']);
        $this->assertSame('Read', $this->fake->access[$conversationId]);
    }

    public function testOutsidersCannotOpenARequestConversation(): void
    {
        $requester = $this->createUser();
        $runner    = $this->createUser();
        $outsider  = $this->createUser();
        $requestId = $this->createRequest($requester);
        (new RequestLifecycle())->accept($requestId, $runner);

        $page = $this->getAs($outsider, 'messages?user_id=' . $requester . '&request_id=' . $requestId);

        $this->assertSame([], $this->fake->conversations);
        $page->assertSee('only message the requester or runner');
        $page->assertDontSee('data-talk-conversation', null);
        $page->assertDontSee('data-talk-composer', null);
    }

    public function testEverySignedInPageRunsTheChatSessionForLiveBadges(): void
    {
        $user = $this->createUser();

        $page = $this->getAs($user, 'dashboard');

        $page->assertOK();
        $page->assertSee('id="talkBoot"', null);
        $page->assertSee('js/talk-session.js', null);
        $page->assertSee('"pb_' . $user . '"', null);
        $page->assertSee('data-unread="messages"', null);
        $page->assertDontSee('sk_test_talkjs_secret');
    }

    public function testChatSessionIsNotRenderedWithoutTalkJs(): void
    {
        config('TalkJS')->appId     = '';
        config('TalkJS')->secretKey = '';
        $user = $this->createUser();

        $this->getAs($user, 'dashboard')->assertDontSee('talkBoot', null);
    }

    public function testKeysAloneDoNotSwitchTalkJsOn(): void
    {
        config('PasaBuy')->talkjsEnabled = false;
        $user = $this->createUser();

        $this->assertTrue(config('TalkJS')->hasKeys());
        $this->assertFalse(config('TalkJS')->isConfigured());
        $this->getAs($user, 'dashboard')->assertDontSee('talkBoot', null);
        $page = $this->getAs($user, 'messages');
        $page->assertSee('Choose a chat');
        $page->assertDontSee('talkjs-container', null);
    }

    public function testDirectMessagesWithoutASharedErrandAreRefused(): void
    {
        $me       = $this->createUser();
        $stranger = $this->createUser();

        $page = $this->getAs($me, 'messages?user_id=' . $stranger);

        $this->assertSame([], $this->fake->conversations, 'No dm_ conversation is created for strangers.');
        $page->assertSee('once one of you accepts');
    }

    public function testMessagesPageListsErrandChatsOnly(): void
    {
        $requester = $this->createUser(['first_name' => 'Rhea']);
        $runner    = $this->createUser(['first_name' => 'Rolly']);
        $stranger  = $this->createUser(['first_name' => 'Stanley']);
        $requestId = $this->createRequest($requester, ['item_name' => 'Rice']);
        $this->createRequest($stranger, ['item_name' => 'Unaccepted soap']);
        (new RequestLifecycle())->accept($requestId, $runner);

        $page = $this->getAs($runner, 'messages');

        $page->assertSee('Errand chats');
        $page->assertSee('messages?user_id=' . $requester . '&amp;request_id=' . $requestId, null);
        $page->assertSee('Your requester');
        $page->assertDontSee('Unaccepted soap');

        $this->getAs($requester, 'messages')->assertSee('Your runner');
    }

    public function testUnreadCountLeavesMessagesToTalkJs(): void
    {
        $user = $this->createUser();

        $data = json_decode($this->getAs($user, 'notifications/unread-count')->getJSON(), true);

        $this->assertNull($data['messages']);
    }
}

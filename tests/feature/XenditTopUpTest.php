<?php

use App\Libraries\XenditClient;
use App\Services\ServiceException;
use CodeIgniter\Config\Services;
use CodeIgniter\HTTP\CURLRequest;
use CodeIgniter\HTTP\Response;
use Config\Xendit;
use Tests\Support\FeatureTestCase;

/**
 * Xendit (GCash/Maya Payment Sessions) top-ups in test mode. No network:
 * flow tests use a fake gateway, client tests use a fake HTTP client that
 * returns responses shaped like Xendit's documented ones.
 *
 * @internal
 */
final class XenditTopUpTest extends FeatureTestCase
{
    private const TOKEN = 'xnd_cb_token_test';

    /** @var object fake gateway state */
    private object $fake;

    protected function setUp(): void
    {
        parent::setUp();

        config('Xendit')->secretKey     = 'xnd_development_fake';
        config('Xendit')->callbackToken = self::TOKEN;

        $this->fake = new class (config('Xendit')) extends XenditClient {
            public array $created = [];
            public array $paid = [];

            public function createCheckout(int $amountCents, string $description, string $reference, ?string $successUrl, ?string $cancelUrl, array $metadata): array
            {
                $id = 'ps-test' . (count($this->created) + 1);
                $this->created[] = compact('amountCents', 'reference', 'successUrl', 'cancelUrl', 'metadata') + ['id' => $id];

                return ['id' => $id, 'checkout_url' => 'https://xen.to/' . $id];
            }

            public function fetchPaid(string $checkoutId): ?array
            {
                return $this->paid[$checkoutId] ?? null;
            }
        };

        Services::injectMock('xendit', $this->fake);
    }

    protected function tearDown(): void
    {
        Services::resetSingle('xendit');

        parent::tearDown();
    }

    private function markPaid(string $id, int $cents, string $method = 'gcash'): void
    {
        $this->fake->paid[$id] = ['payment_id' => 'py-' . $id, 'amount_cents' => $cents, 'method' => $method, 'livemode' => false];
    }

    private function webhook(string $sessionId, ?string $token = self::TOKEN, string $event = 'payment_session.completed'): \CodeIgniter\Test\TestResponse
    {
        $body = json_encode(['event' => $event, 'business_id' => 'biz', 'created' => date(DATE_ATOM), 'data' => [
            'payment_session_id' => $sessionId, 'status' => 'COMPLETED', 'payment_id' => 'py-' . $sessionId,
        ]]);

        Services::resetSingle('security');

        $headers = ['Content-Type' => 'application/json'];

        if ($token !== null) {
            $headers['x-callback-token'] = $token;
        }

        return $this->withHeaders($headers)->withBody($body)->post('webhooks/xendit');
    }

    public function testXenditIsPreferredOverPayMongoAndRedirectsToPaymentLink(): void
    {
        config('PayMongo')->secretKey = 'sk_test_also_set';
        $user = $this->createUser();

        $response = $this->postAs($user, 'wallet/top-up', ['amount' => '250']);

        $this->assertSame('https://xen.to/ps-test1', $response->getRedirectUrl());
        $this->assertSame(25000, $this->fake->created[0]['amountCents']);
        $row = $this->db->table('wallet_topups')->get()->getRow();
        $this->assertSame('xendit', $row->provider);
        $this->assertSame('ps-test1', $row->checkout_session_id);
        $this->assertSame('0.00', $this->balance($user), 'Nothing is credited before payment is confirmed.');
    }

    public function testPublicBaseUrlIsUsedForReturnUrls(): void
    {
        config('Xendit')->publicBaseUrl = 'https://demo.example.test/pb/';
        $user = $this->createUser();

        $this->postAs($user, 'wallet/top-up', ['amount' => '150']);

        $this->assertStringStartsWith('https://demo.example.test/pb/wallet/top-up/return?topup=', $this->fake->created[0]['successUrl']);
        $this->assertSame('https://demo.example.test/pb/wallet?topup=cancelled', $this->fake->created[0]['cancelUrl']);
    }

    public function testWebhookCreditsOnlyAfterReFetchingAndOnlyOnce(): void
    {
        $user = $this->createUser();
        $this->postAs($user, 'wallet/top-up', ['amount' => '250']);

        // The webhook says "completed", but Xendit's API doesn't confirm it yet.
        $this->webhook('ps-test1')->assertOK();
        $this->assertSame('0.00', $this->balance($user));

        $this->markPaid('ps-test1', 25000);
        $this->webhook('ps-test1')->assertOK();
        $this->webhook('ps-test1')->assertOK();

        $this->assertSame('250.00', $this->balance($user));
        $this->assertSame(1, $this->countRows('payments', ['user_id' => $user]));
        $entry = $this->db->table('payments')->where('user_id', $user)->get()->getRowArray();
        $this->assertSame('gcash', $entry['source']);
        $this->assertSame('xd_ps-test1', $entry['idempotency_key']);
        $this->assertStringContainsString('Xendit test mode', $entry['description']);
        $this->assertSame('paid', $this->db->table('wallet_topups')->get()->getRow()->status);
    }

    public function testWebhookWithWrongOrMissingTokenIsRejected(): void
    {
        $user = $this->createUser();
        $this->postAs($user, 'wallet/top-up', ['amount' => '250']);
        $this->markPaid('ps-test1', 25000);

        $this->assertSame(401, $this->webhook('ps-test1', 'wrong')->response()->getStatusCode());
        $this->assertSame(401, $this->webhook('ps-test1', null)->response()->getStatusCode());
        $this->assertSame('0.00', $this->balance($user));
    }

    public function testWebhookIsRejectedWhenNoTokenIsConfigured(): void
    {
        config('Xendit')->callbackToken = '';

        $this->assertSame(401, $this->webhook('ps-test1', '')->response()->getStatusCode());
    }

    public function testOtherEventsAreIgnored(): void
    {
        $user = $this->createUser();
        $this->postAs($user, 'wallet/top-up', ['amount' => '250']);
        $this->markPaid('ps-test1', 25000);

        $this->webhook('ps-test1', self::TOKEN, 'payment_session.expired')->assertOK();

        $this->assertSame('0.00', $this->balance($user));
    }

    public function testAmountMismatchIsNotCredited(): void
    {
        $user = $this->createUser();
        $this->postAs($user, 'wallet/top-up', ['amount' => '250']);
        $this->markPaid('ps-test1', 100);

        $this->webhook('ps-test1')->assertOK();

        $this->assertSame('0.00', $this->balance($user));
        $this->assertSame('pending', $this->db->table('wallet_topups')->get()->getRow()->status);
    }

    public function testCheckPaymentButtonReconcilesWithXendit(): void
    {
        $user = $this->createUser();
        $this->postAs($user, 'wallet/top-up', ['amount' => '300']);
        $topUpId = (int) $this->db->table('wallet_topups')->get()->getRow()->id;

        $this->getAs($user, 'wallet')->assertSee('Check payment');

        $this->postAs($user, 'wallet/top-up/check', ['topup' => $topUpId]);
        $this->assertSame('0.00', $this->balance($user));

        $this->markPaid('ps-test1', 30000, 'paymaya');
        $this->postAs($user, 'wallet/top-up/check', ['topup' => $topUpId]);

        $this->assertSame('300.00', $this->balance($user));
        $this->assertSame('paymaya', $this->db->table('payments')->where('user_id', $user)->get()->getRow()->source);
        $this->getAs($user, 'wallet')->assertDontSee('Check payment');
    }

    public function testCannotCheckSomeoneElsesTopUp(): void
    {
        $owner = $this->createUser();
        $this->postAs($owner, 'wallet/top-up', ['amount' => '300']);
        $topUpId = (int) $this->db->table('wallet_topups')->get()->getRow()->id;
        $this->markPaid('ps-test1', 30000);

        $this->postAs($this->createUser(), 'wallet/top-up/check', ['topup' => $topUpId]);

        $this->assertSame('pending', $this->db->table('wallet_topups')->get()->getRow()->status);
        $this->assertSame('0.00', $this->balance($owner));
    }

    // ---- XenditClient against Xendit-shaped HTTP responses -------------

    /** @param list<array{0:int,1:array}> $responses */
    private function clientWith(array $responses, array &$calls, string $key = 'xnd_development_fake'): XenditClient
    {
        $http = $this->createMock(CURLRequest::class);
        $http->method('request')->willReturnCallback(static function (string $method, string $url, array $options) use (&$responses, &$calls) {
            $calls[] = compact('method', 'url', 'options');
            [$status, $body] = array_shift($responses);

            return (new Response(config('App')))->setStatusCode($status)->setBody(json_encode($body));
        });

        $config            = new Xendit();
        $config->secretKey = $key;

        return new XenditClient($config, $http);
    }

    public function testClientCreatesPaymentSessionInPesosWithHttpsReturnUrlsOnly(): void
    {
        $calls  = [];
        $client = $this->clientWith([[201, ['payment_session_id' => 'ps-abc', 'payment_link_url' => 'https://xen.to/abc', 'status' => 'ACTIVE']]], $calls);

        $result = $client->createCheckout(25050, 'Top-up', 'PB-TOPUP-1', 'http://localhost/wallet/top-up/return', 'https://x.test/cancel', ['topup_id' => '1']);

        $this->assertSame(['id' => 'ps-abc', 'checkout_url' => 'https://xen.to/abc'], $result);
        $this->assertSame('POST', $calls[0]['method']);
        $this->assertSame('https://api.xendit.co/sessions', $calls[0]['url']);
        $this->assertSame(['xnd_development_fake', ''], $calls[0]['options']['auth']);
        $json = $calls[0]['options']['json'];
        $this->assertSame(250.5, $json['amount']);
        $this->assertSame(['GCASH', 'PAYMAYA'], $json['allowed_payment_channels']);
        $this->assertSame('PAYMENT_LINK', $json['mode']);
        $this->assertArrayNotHasKey('success_return_url', $json, 'Xendit rejects non-HTTPS return URLs.');
        $this->assertSame('https://x.test/cancel', $json['cancel_return_url']);
    }

    public function testClientConfirmsPaymentThroughTheSessionAndPaymentApis(): void
    {
        $calls  = [];
        $client = $this->clientWith([
            [200, ['payment_session_id' => 'ps-abc', 'status' => 'COMPLETED', 'payment_id' => 'py-123', 'amount' => 250]],
            [200, ['payment_id' => 'py-123', 'status' => 'SUCCEEDED', 'currency' => 'PHP', 'channel_code' => 'GCASH', 'request_amount' => 250, 'captures' => [['capture_amount' => 250]]]],
        ], $calls);

        $paid = $client->fetchPaid('ps-abc');

        $this->assertSame(['payment_id' => 'py-123', 'amount_cents' => 25000, 'method' => 'gcash', 'livemode' => false], $paid);
        $this->assertSame('https://api.xendit.co/v3/payments/py-123', $calls[1]['url']);
        $this->assertSame('2024-11-11', $calls[1]['options']['headers']['api-version']);
    }

    public function testClientReportsUnpaidSessions(): void
    {
        $calls  = [];
        $client = $this->clientWith([[200, ['payment_session_id' => 'ps-abc', 'status' => 'ACTIVE']]], $calls);

        $this->assertNull($client->fetchPaid('ps-abc'));
        $this->assertCount(1, $calls);
    }

    public function testClientRefusesLiveKeysAndBadIds(): void
    {
        $calls = [];

        try {
            $this->clientWith([], $calls, 'xnd_production_real')->fetchPaid('ps-abc');
            $this->fail('Live keys must be refused.');
        } catch (ServiceException) {
            $this->assertSame([], $calls, 'No request is sent with a live key.');
        }

        $this->expectException(ServiceException::class);
        $this->clientWith([], $calls)->fetchPaid('../balance');
    }

    public function testCallbackTokenComparison(): void
    {
        $this->assertTrue(XenditClient::verifyCallbackToken('abc', 'abc'));
        $this->assertFalse(XenditClient::verifyCallbackToken('abd', 'abc'));
        $this->assertFalse(XenditClient::verifyCallbackToken('', ''));
    }
}

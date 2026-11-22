<?php

use App\Libraries\PayMongoClient;
use CodeIgniter\Config\Services;
use Tests\Support\FeatureTestCase;

/**
 * PayMongo (GCash/Maya) top-ups in test mode. The HTTP client is replaced
 * with a fake, so no network calls are made; webhook requests are signed
 * exactly as PayMongo documents: HMAC-SHA256("<t>.<raw body>", secret).
 *
 * @internal
 */
final class PayMongoTopUpTest extends FeatureTestCase
{
    private const SECRET = 'whsk_test_secret';

    /** @var object fake client state */
    private object $fake;

    protected function setUp(): void
    {
        parent::setUp();

        $config                = config('PayMongo');
        $config->secretKey     = 'sk_test_fake';
        $config->webhookSecret = self::SECRET;

        $this->fake = new class (config('PayMongo')) extends PayMongoClient {
            public array $created = [];
            public array $sessions = [];

            public function createCheckoutSession(int $amountCents, string $description, string $successUrl, string $cancelUrl, string $reference, array $metadata): array
            {
                $id = 'cs_test' . (count($this->created) + 1);
                $this->created[] = compact('amountCents', 'successUrl', 'cancelUrl', 'reference', 'metadata') + ['id' => $id];

                return ['id' => $id, 'checkout_url' => 'https://checkout.paymongo.com/' . $id];
            }

            public function retrieveCheckoutSession(string $id): array
            {
                return $this->sessions[$id] ?? ['id' => $id, 'attributes' => ['payments' => []]];
            }
        };

        Services::injectMock('paymongo', $this->fake);
    }

    protected function tearDown(): void
    {
        config('PayMongo')->secretKey     = '';
        config('PayMongo')->webhookSecret = '';
        Services::resetSingle('paymongo');

        parent::tearDown();
    }

    private function paidSession(string $id, int $amount, string $method = 'gcash'): array
    {
        return ['id' => $id, 'type' => 'checkout_session', 'attributes' => [
            'livemode' => false,
            'payments' => [['id' => 'pay_' . $id, 'attributes' => ['amount' => $amount, 'currency' => 'PHP', 'status' => 'paid', 'source' => ['type' => $method]]]],
        ]];
    }

    private function webhook(array $session, ?string $signature = null, ?int $t = null): \CodeIgniter\Test\TestResponse
    {
        $body = json_encode(['data' => ['id' => 'evt_1', 'type' => 'event', 'attributes' => [
            'type' => 'checkout_session.payment.paid', 'livemode' => false, 'data' => $session,
        ]]]);
        $t ??= time();
        $signature ??= 't=' . $t . ',te=' . hash_hmac('sha256', $t . '.' . $body, self::SECRET) . ',li=';

        Services::resetSingle('security');

        return $this->withHeaders(['Paymongo-Signature' => $signature, 'Content-Type' => 'application/json'])
            ->withBody($body)
            ->post('webhooks/paymongo');
    }

    public function testStartingATopUpRedirectsToPayMongoCheckout(): void
    {
        $user = $this->createUser();

        $response = $this->postAs($user, 'wallet/top-up', ['amount' => '250']);

        $this->assertSame('https://checkout.paymongo.com/cs_test1', $response->getRedirectUrl());
        $created = $this->fake->created[0];
        $this->assertSame(25000, $created['amountCents']);
        $this->assertStringContainsString('wallet/top-up/return?topup=', $created['successUrl']);
        $this->assertSame('pending', $this->db->table('wallet_topups')->get()->getRow()->status);
        $this->assertSame('0.00', $this->balance($user), 'Nothing is credited before payment is confirmed.');
    }

    public function testTopUpAmountLimits(): void
    {
        $user = $this->createUser();

        $this->postAs($user, 'wallet/top-up', ['amount' => '50']);

        $this->assertStringContainsString('₱100.00', (string) $this->flash('error'));
        $this->assertSame([], $this->fake->created);
    }

    public function testSignedWebhookCreditsOnceEvenIfDeliveredTwice(): void
    {
        $user = $this->createUser();
        $this->postAs($user, 'wallet/top-up', ['amount' => '250']);

        $this->webhook($this->paidSession('cs_test1', 25000))->assertOK();
        $this->webhook($this->paidSession('cs_test1', 25000))->assertOK();

        $this->assertSame('250.00', $this->balance($user));
        $entry = $this->db->table('payments')->where('user_id', $user)->get()->getRowArray();
        $this->assertSame('top_up', $entry['type']);
        $this->assertSame('gcash', $entry['source']);
        $this->assertSame(1, $this->countRows('payments', ['user_id' => $user]));
        $this->assertSame('paid', $this->db->table('wallet_topups')->get()->getRow()->status);
    }

    public function testWebhookWithBadSignatureIsRejected(): void
    {
        $user = $this->createUser();
        $this->postAs($user, 'wallet/top-up', ['amount' => '250']);

        $response = $this->webhook($this->paidSession('cs_test1', 25000), 't=' . time() . ',te=deadbeef,li=');

        $this->assertSame(401, $response->response()->getStatusCode());
        $this->assertSame('0.00', $this->balance($user));
    }

    public function testOldWebhookSignatureIsRejectedAsReplay(): void
    {
        $user = $this->createUser();
        $this->postAs($user, 'wallet/top-up', ['amount' => '250']);

        $response = $this->webhook($this->paidSession('cs_test1', 25000), null, time() - 3600);

        $this->assertSame(401, $response->response()->getStatusCode());
        $this->assertSame('0.00', $this->balance($user));
    }

    public function testAmountMismatchIsNotCredited(): void
    {
        $user = $this->createUser();
        $this->postAs($user, 'wallet/top-up', ['amount' => '250']);

        $this->webhook($this->paidSession('cs_test1', 100))->assertOK();

        $this->assertSame('0.00', $this->balance($user));
        $this->assertSame('pending', $this->db->table('wallet_topups')->get()->getRow()->status);
    }

    public function testReturningFromCheckoutVerifiesWithPayMongoBeforeCrediting(): void
    {
        $user = $this->createUser();
        $this->postAs($user, 'wallet/top-up', ['amount' => '300']);
        $topUpId = (int) $this->db->table('wallet_topups')->get()->getRow()->id;

        // Not paid yet: returning to the success URL must not credit anything.
        $this->getAs($user, 'wallet/top-up/return?topup=' . $topUpId);
        $this->assertSame('0.00', $this->balance($user));

        $this->fake->sessions['cs_test1'] = $this->paidSession('cs_test1', 30000, 'paymaya');
        $this->getAs($user, 'wallet/top-up/return?topup=' . $topUpId);

        $this->assertSame('300.00', $this->balance($user));
        $this->assertSame('paymaya', $this->db->table('payments')->where('user_id', $user)->get()->getRow()->source);
    }

    public function testCannotReconcileSomeoneElsesTopUp(): void
    {
        $owner = $this->createUser();
        $this->postAs($owner, 'wallet/top-up', ['amount' => '300']);
        $topUpId = (int) $this->db->table('wallet_topups')->get()->getRow()->id;
        $this->fake->sessions['cs_test1'] = $this->paidSession('cs_test1', 30000);

        $this->getAs($this->createUser(), 'wallet/top-up/return?topup=' . $topUpId);

        $this->assertSame('pending', $this->db->table('wallet_topups')->get()->getRow()->status);
    }

    public function testLiveKeysAreRefusedInTheDemo(): void
    {
        $config = clone config('PayMongo');
        $config->secretKey = 'sk_live_xxx';

        $this->expectException(\App\Services\ServiceException::class);
        (new PayMongoClient($config))->retrieveCheckoutSession('cs_test1');
    }

    public function testSignatureHelperMatchesDocumentedFormat(): void
    {
        $body = '{"x":1}';
        $sig  = hash_hmac('sha256', '1700000000.' . $body, 'k');

        $this->assertTrue(PayMongoClient::verifySignature($body, "t=1700000000,te={$sig},li=", 'k', false, 0));
        $this->assertFalse(PayMongoClient::verifySignature($body, "t=1700000000,te=,li={$sig}", 'k', false, 0), 'Test events use te, not li.');
        $this->assertTrue(PayMongoClient::verifySignature($body, "t=1700000000,te=,li={$sig}", 'k', true, 0));
        $this->assertFalse(PayMongoClient::verifySignature($body . ' ', "t=1700000000,te={$sig},li=", 'k', false, 0), 'Any body change breaks the signature.');
    }
}

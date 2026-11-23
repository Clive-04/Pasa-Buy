<?php

use App\Services\RequestLifecycle;
use Tests\Support\FeatureTestCase;

/**
 * Server-side rate limits (security finding S4). Limits are lowered for the
 * test so a few requests reach them; production values live in Config\PasaBuy.
 *
 * @internal
 */
final class RateLimitTest extends FeatureTestCase
{
    private array $savedLimits;

    protected function setUp(): void
    {
        parent::setUp();
        $this->savedLimits = config('PasaBuy')->rateLimits;
    }

    protected function tearDown(): void
    {
        config('PasaBuy')->rateLimits = $this->savedLimits;
        parent::tearDown();
    }

    private function limit(string $action, int $max, int $seconds = 3600): void
    {
        config('PasaBuy')->rateLimits[$action] = [$max, $seconds];
    }

    private function requestForm(array $overrides = []): array
    {
        return $overrides + [
            'item_name' => 'Rice 5kg', 'category' => 'food', 'quantity' => '1', 'store_name' => 'Store',
            'store_address' => 'Store St', 'delivery_address' => 'Home St', 'contact_number' => '09171234567',
            'estimated_price' => '100', 'service_fee' => '20', 'payment_method' => 'cod', 'urgency' => 'normal',
        ];
    }

    public function testPostingRequestsIsLimitedAndKeepsTheTypedForm(): void
    {
        $this->limit('post-request', 2);
        $user = $this->createUser();

        $this->postAs($user, 'request/process', $this->requestForm());
        $this->postAs($user, 'request/process', $this->requestForm());
        $refused = $this->postAs($user, 'request/process', $this->requestForm(['item_name' => 'Third one']));

        $this->assertRedirectsTo($refused, 'post-request');
        $this->assertStringStartsWith('Too many new requests in a short time. Please wait', (string) $this->flash('error'));
        $this->assertNotSame('', $refused->response()->getHeaderLine('Retry-After'));
        $this->assertSame(2, $this->countRows('requests'));
        $this->assertSame('Third one', $_SESSION['_ci_old_input']['post']['item_name'] ?? null, 'The form is refilled after the refusal.');

        // Another user has their own allowance.
        $this->postAs($this->createUser(), 'request/process', $this->requestForm());
        $this->assertSame(3, $this->countRows('requests'));
    }

    public function testInvalidPostsDoNotUseUpTheAllowance(): void
    {
        $this->limit('post-request', 1);
        $user = $this->createUser();

        $this->postAs($user, 'request/process', $this->requestForm(['item_name' => '']));
        $this->postAs($user, 'request/process', $this->requestForm());

        $this->assertSame(1, $this->countRows('requests'));
    }

    public function testRatingsAreLimited(): void
    {
        $this->limit('rating', 1);
        $requester = $this->createUser();
        $runner    = $this->createUser();
        $ids       = [];

        foreach ([1, 2] as $n) {
            $id = $this->createRequest($requester);
            (new RequestLifecycle())->accept($id, $runner);
            $this->db->table('requests')->where('id', $id)->update(['status' => 'delivered']);
            $ids[] = $id;
        }

        $this->postAs($requester, 'request/' . $ids[0] . '/rate', ['rating' => '5']);
        $this->assertRedirectsTo($this->postAs($requester, 'request/' . $ids[1] . '/rate', ['rating' => '4']), 'request-details/' . $ids[1]);
        $this->assertStringStartsWith('Too many ratings', (string) $this->flash('error'));
        $this->assertSame(1, $this->countRows('user_ratings'));
    }

    public function testTopUpStartAndPaymentChecksAreLimited(): void
    {
        $this->limit('topup-start', 1);
        $this->limit('payment-check', 1, 60);
        $user = $this->createUser();

        $this->postAs($user, 'wallet/top-up', ['amount' => '100', 'idempotency_key' => bin2hex(random_bytes(16))]);
        $this->assertSame('100.00', $this->balance($user));

        $this->postAs($user, 'wallet/top-up', ['amount' => '100', 'idempotency_key' => bin2hex(random_bytes(16))]);
        $this->assertStringStartsWith('Too many top-up attempts', (string) $this->flash('error'));
        $this->assertSame('100.00', $this->balance($user), 'A refused top-up adds nothing.');

        $this->postAs($user, 'wallet/top-up/check', ['topup' => '999']);
        $this->assertSame('That top-up could not be found.', $this->flash('error'));
        $this->postAs($user, 'wallet/top-up/check', ['topup' => '999']);
        $this->assertStringStartsWith('Too many payment checks', (string) $this->flash('error'));
    }

    public function testWebhooksAreNotRateLimited(): void
    {
        // Providers retry failed deliveries; throttling them could drop a
        // legitimate payment confirmation. They are authenticated instead.
        $this->assertArrayNotHasKey('webhook', config('PasaBuy')->rateLimits);
    }
}

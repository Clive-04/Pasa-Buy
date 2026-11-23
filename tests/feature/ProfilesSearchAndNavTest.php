<?php

use App\Services\RequestLifecycle;
use App\Services\RatingService;
use Tests\Support\FeatureTestCase;

/**
 * R3 public profiles and search, R2 navigation rail and action badges.
 *
 * @internal
 */
final class ProfilesSearchAndNavTest extends FeatureTestCase
{
    private function deliveredErrand(int $requester, int $runner, array $overrides = []): int
    {
        $id = $this->createRequest($requester, $overrides + ['payment_method' => 'cod']);
        $this->fund($runner, '50.00');
        (new RequestLifecycle())->accept($id, $runner);
        $this->db->table('requests')->where('id', $id)->update(['status' => 'delivered', 'payment_status' => 'paid_cod']);
        $this->db->table('accepted_requests')->where('request_id', $id)->update(['status' => 'delivered']);

        return $id;
    }

    // ------------------------------------------------------------ profiles

    public function testPublicProfileShowsRealCountsDistributionAndReviewsWithoutPrivateDetails(): void
    {
        $runner = $this->createUser(['first_name' => 'Rolly', 'address' => 'Secret Street 9', 'phone_number' => '09171112222']);
        $alice  = $this->createUser(['first_name' => 'Alice']);
        $bob    = $this->createUser(['first_name' => 'Bob']);

        $a = $this->deliveredErrand($alice, $runner, ['item_name' => 'Pregnancy test kit', 'delivery_address' => 'Alice Home 1']);
        $b = $this->deliveredErrand($bob, $runner, ['item_name' => 'Rice']);
        (new RatingService())->rateDelivery($alice, $a, 5, 'Fast and friendly');
        (new RatingService())->rateDelivery($bob, $b, 3, '');
        $this->createRequest($runner); // runner also posted one request

        $page = $this->getAs($bob, 'users/' . $runner);
        $page->assertOK();
        $page->assertSee('<h1>Rolly User</h1>', null);
        $page->assertSee('4.0');
        $page->assertSee('Deliveries completed');
        $page->assertSee('<dd>2</dd>', null);
        $page->assertSee('<dd>1</dd>', null);
        $page->assertSee('Fast and friendly');
        $page->assertSee('their requester');

        // Private: item names, addresses, contact details, prices.
        $page->assertDontSee('Pregnancy test kit');
        $page->assertDontSee('Alice Home 1');
        $page->assertDontSee('Secret Street 9');
        $page->assertDontSee('09171112222');
        $page->assertDontSee('completion rate');
        $page->assertDontSee('reliab');

        // Bob shared an errand: he sees it, and the Message button.
        $page->assertSee('Your errands with Rolly');
        $page->assertSee('request-details/' . $b, null);
        $page->assertDontSee('request-details/' . $a, null);
        $page->assertSee('messages?user_id=' . $runner, null);

        // A stranger sees neither the shared errands nor a Message button.
        $stranger = $this->getAs($this->createUser(), 'users/' . $runner);
        $stranger->assertDontSee('Your errands with');
        $stranger->assertDontSee('messages?user_id=' . $runner, null);
        $stranger->assertSee('once one of you accepts');
        $stranger->assertSee('Report');
    }

    public function testReviewsArePaginated(): void
    {
        $runner = $this->createUser();

        for ($i = 1; $i <= 7; $i++) {
            $rater = $this->createUser();
            (new RatingService())->rateDelivery($rater, $this->deliveredErrand($rater, $runner), 4, 'Review number ' . $i);
            $this->db->table('user_ratings')->where('rater_id', $rater)->update(['created_at' => date('Y-m-d H:i:s', time() - (10 - $i) * 60)]);
        }

        $viewer = $this->createUser();
        $first  = $this->getAs($viewer, 'users/' . $runner);
        $first->assertSee('Review number 7');
        $first->assertDontSee('Review number 2');
        $first->assertSee('Page 1 of 2');

        $second = $this->getAs($viewer, 'users/' . $runner . '?reviews=2');
        $second->assertSee('Review number 2');
        $second->assertDontSee('Review number 7');
    }

    public function testOwnProfileStillShowsAllRegistrationDetailsButNoPassword(): void
    {
        $me = $this->createUser(['first_name' => 'Mia', 'email' => 'mia@example.test', 'phone_number' => '09179998888', 'address' => 'Purok 1']);

        $page = $this->getAs($me, 'profile');
        $page->assertSee('mia@example.test');
        $page->assertSee('09179998888');
        $page->assertSee('Purok 1');
        $page->assertDontSee('$2y$', null);
    }

    public function testAdminsHaveNoPublicProfile(): void
    {
        $admin = $this->createUser(['role' => 'admin']);

        $this->assertRedirectsTo($this->getAs($this->createUser(), 'users/' . $admin), 'users/search');
    }

    // -------------------------------------------------------------- search

    public function testSearchNeedsTwoCharactersAndPaginates(): void
    {
        $me = $this->createUser();

        for ($i = 1; $i <= 14; $i++) {
            $this->createUser(['first_name' => 'Ana' . str_pad((string) $i, 2, '0', STR_PAD_LEFT)]);
        }

        $short = $this->getAs($me, 'users/search?q=a');
        $short->assertSee('Type at least 2 characters');
        $short->assertDontSee('Ana01');

        $page1 = $this->getAs($me, 'users/search?q=ana');
        $page1->assertSee('14 people match');
        $page1->assertSee('Ana01');
        $page1->assertDontSee('Ana13');
        $page1->assertSee('Page 1 of 2');
        $page1->assertSee('No ratings yet');
        $page1->assertDontSee('id="topbarSearch"', null);
        $this->assertSame(1, substr_count($page1->getBody(), 'role="search"'), 'Only one search box on the search page.');

        $this->getAs($me, 'users/search?q=ana&page=2')->assertSee('Ana13');
    }

    public function testSearchNeverMatchesOrShowsEmailOrPhone(): void
    {
        $me = $this->createUser();
        $this->createUser(['first_name' => 'Hidden', 'email' => 'zzfindme@example.test']);

        $page = $this->getAs($me, 'users/search?q=zzfindme');
        $page->assertSee('No one found');
    }

    public function testSearchIsRateLimited(): void
    {
        $saved = config('PasaBuy')->rateLimits;
        config('PasaBuy')->rateLimits['search'] = [1, 60];

        try {
            $me = $this->createUser();
            $this->getAs($me, 'users/search?q=test')->assertOK();
            $limited = $this->getAs($me, 'users/search?q=test');
            $limited->assertStatus(429);
            $limited->assertSee('Too many searches');
        } finally {
            config('PasaBuy')->rateLimits = $saved;
        }
    }

    // ---------------------------------------------------------- navigation

    public function testRailHasWalletNotificationsAndOneProfileEntry(): void
    {
        $me   = $this->createUser();
        $page = $this->getAs($me, 'wallet');

        $html = $page->getBody();
        $page->assertSee('href="' . base_url('notifications') . '" class="side-link"', null);
        $page->assertSee('href="' . base_url('wallet') . '" class="side-link is-active" aria-current="page"', null);
        $page->assertDontSee('side-profile', null);
        // The footer's "My profile" text link is not navigation chrome; check the rail itself.
        preg_match('#<aside class="dashboard-sidebar.*?</aside>#s', $html, $rail);
        $this->assertStringNotContainsString('href="' . base_url('profile') . '"', $rail[0], 'No profile tile in the rail.');
        $this->assertStringContainsString('class="topbar-user', $html, 'The top-bar profile entry stays.');
    }

    public function testActionBadgesCountEachErrandOnceAndMatchTheDestinationList(): void
    {
        $requester = $this->createUser();
        $runner    = $this->createUser();
        $this->fund($runner, '100.00');
        $this->fund($requester, '500.00');

        // 1) wallet request accepted, not paid: requester must pay; runner can start.
        $pay = $this->createRequest($requester, ['item_name' => 'Needs payment', 'payment_method' => 'wallet']);
        (new RequestLifecycle())->accept($pay, $runner);

        // 2) delivered, unrated by both: each side must rate.
        $rate = $this->deliveredErrand($requester, $runner, ['item_name' => 'Needs rating']);

        // 3) cancelled + pending: nothing to do.
        $this->createRequest($requester, ['item_name' => 'Still pending']);

        $mine = $this->getAs($requester, 'my-requests');
        $mine->assertSee('2 requests need you');
        $mine->assertSee('Pay your runner');
        $mine->assertSee('Rate this delivery');
        $mine->assertSee('2<span class="visually-hidden"> requests need you (pay or rate)</span>', null);

        $deliveries = $this->getAs($runner, 'accepted-requests');
        $deliveries->assertSee('2 deliveries need you');
        $deliveries->assertSee('Mark as On the way');

        // Rating clears that item; paying clears the other.
        (new RatingService())->rateDelivery($requester, $rate, 5, '');
        $this->postAs($requester, 'request/pay-runner/' . $pay);
        $this->getAs($requester, 'my-requests')->assertDontSee('need you');
        $this->getAs($requester, 'dashboard')->assertDontSee('badge-action', null);
    }

    public function testDemoNoticeOnlyAppearsAtTopUp(): void
    {
        $me = $this->createUser();

        $this->getAs($me, 'dashboard')->assertDontSee('demo-banner', null);
        $this->getAs($me, 'dashboard')->assertDontSee('Demo version:');
        $this->getAs(null, 'login')->assertDontSee('Demo version:');

        $wallet = $this->getAs($me, 'wallet');
        $wallet->assertSee('class="demo-notice"', null);
        $wallet->assertSee('wallet balances are simulated credits, not real money');
    }

    public function testAdminRailHighlightsTheCurrentPage(): void
    {
        $admin = $this->createUser(['role' => 'admin']);

        $this->getAs($admin, 'admin/dashboard?tab=delivered', 'admin')
            ->assertSee('href="' . base_url('admin/dashboard?tab=delivered') . '" class="side-link is-active" aria-current="page"', null);
        $this->getAs($admin, 'admin/reports', 'admin')
            ->assertSee('href="' . base_url('admin/reports') . '" class="side-link is-active" aria-current="page"', null);
    }
}

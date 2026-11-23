<?php

use App\Services\ChatPolicy;
use App\Services\RequestLifecycle;
use App\Services\ServiceException;
use Tests\Support\FeatureTestCase;

/**
 * Admin area: account suspension (and its effects everywhere), the
 * Requests / Users / Top-ups pages, filters and the CSV export.
 *
 * @internal
 */
final class AdminAreaTest extends FeatureTestCase
{
    private function user(int $id): array
    {
        return $this->db->table('users')->where('id', $id)->get()->getRowArray();
    }

    private function suspend(int $admin, int $target, array $data = []): \CodeIgniter\Test\TestResponse
    {
        return $this->postAs($admin, 'admin/users/' . $target . '/suspend', $data + ['reason' => 'Repeated no-shows'], 'admin');
    }

    // ----------------------------------------------------------- suspension

    public function testAdminCanSuspendAndRestoreAnAccountWithAReason(): void
    {
        $admin  = $this->createUser(['role' => 'admin']);
        $member = $this->createUser();

        $this->assertRedirectsTo($this->suspend($admin, $member), 'admin/users');
        $row = $this->user($member);
        $this->assertSame('suspended', $row['status']);
        $this->assertSame('Repeated no-shows', $row['suspension_reason']);
        $this->assertSame($admin, (int) $row['suspended_by']);
        $this->assertNotNull($row['suspended_at']);

        $this->assertRedirectsTo($this->postAs($admin, 'admin/users/' . $member . '/restore', [], 'admin'), 'admin/users');
        $row = $this->user($member);
        $this->assertSame('active', $row['status']);
        $this->assertNull($row['suspension_reason']);
    }

    public function testSuspensionNeedsAReasonAndNeverTargetsAdminsOrYourself(): void
    {
        $admin      = $this->createUser(['role' => 'admin']);
        $otherAdmin = $this->createUser(['role' => 'admin']);
        $member     = $this->createUser();

        $this->suspend($admin, $member, ['reason' => ' ']);
        $this->assertSame('active', $this->user($member)['status']);
        $this->assertStringContainsString('reason', $this->flash('error'));

        $this->suspend($admin, $otherAdmin);
        $this->assertSame('active', $this->user($otherAdmin)['status']);

        $this->suspend($admin, $admin);
        $this->assertSame('active', $this->user($admin)['status']);
    }

    public function testMembersCannotSuspendAnyone(): void
    {
        $member = $this->createUser();
        $target = $this->createUser();

        $this->assertRedirectsTo($this->postAs($member, 'admin/users/' . $target . '/suspend', ['reason' => 'nope']), 'dashboard');
        // A forged session role is not enough: the role is re-read from the database.
        $this->assertRedirectsTo($this->postAs($member, 'admin/users/' . $target . '/suspend', ['reason' => 'nope'], 'admin'), 'dashboard');
        $this->assertSame('active', $this->user($target)['status']);
    }

    public function testSuspendingCanCancelPendingRequestsButLeavesErrandsInProgress(): void
    {
        $admin    = $this->createUser(['role' => 'admin']);
        $member   = $this->createUser();
        $pending  = $this->createRequest($member);
        $accepted = $this->createRequest($member);
        (new RequestLifecycle())->accept($accepted, $this->createUser());

        $this->suspend($admin, $member, ['cancel_pending' => '1']);

        $this->assertSame('cancelled', $this->requestRow($pending)['status']);
        $this->assertSame('accepted', $this->requestRow($accepted)['status']);
        $this->assertSame(2, $this->countRows('requests'), 'Nothing is deleted.');
        $this->assertStringContainsString('1 pending request was cancelled', $this->flash('success'));
    }

    public function testSuspendingWithoutTheBoxLeavesPendingRequestsAlone(): void
    {
        $admin   = $this->createUser(['role' => 'admin']);
        $member  = $this->createUser();
        $pending = $this->createRequest($member);

        $this->suspend($admin, $member);

        $this->assertSame('pending', $this->requestRow($pending)['status']);
    }

    public function testASuspendedAccountCannotLogInAndOnlyLearnsWhyWithTheRightPassword(): void
    {
        $this->createUser(['email' => 'held@example.test', 'status' => 'suspended', 'suspension_reason' => 'x']);

        $this->postAs(null, 'login/process', ['email' => 'held@example.test', 'password' => 'wrong-password']);
        $this->assertSame('Invalid email or password.', $this->flash('error'));

        $response = $this->postAs(null, 'login/process', ['email' => 'held@example.test', 'password' => self::PASSWORD]);
        $this->assertTrue($response->isRedirect());
        $this->assertStringContainsString('suspended', $this->flash('error'));
        $this->assertArrayNotHasKey('isLoggedIn', $_SESSION);
    }

    public function testAnOpenSessionEndsOnTheNextRequestAfterSuspension(): void
    {
        $member = $this->createUser(['status' => 'suspended']);

        $this->assertRedirectsTo($this->getAs($member, 'dashboard'), 'login');
        $this->assertStringContainsString('suspended', $this->flash('error'));
        $this->assertArrayNotHasKey('user_id', $_SESSION);
        $this->assertArrayNotHasKey('isLoggedIn', $_SESSION);
    }

    public function testNobodyCanMessageASuspendedAccountAndRunnersDoNotSeeTheirRequests(): void
    {
        $requester = $this->createUser();
        $runner    = $this->createUser();
        $requestId = $this->createRequest($requester, ['item_name' => 'Hidden while suspended']);
        $other     = $this->createRequest($this->createUser(), ['item_name' => 'Still listed']);

        $this->db->table('users')->where('id', $requester)->update(['status' => 'suspended']);

        $browse = $this->getAs($runner, 'browse-requests');
        $browse->assertDontSee('Hidden while suspended');
        $browse->assertSee('Still listed');

        try {
            (new RequestLifecycle())->accept($requestId, $runner);
            $this->fail('Accepting a suspended member’s request must fail.');
        } catch (ServiceException) {
            $this->assertSame('pending', $this->requestRow($requestId)['status']);
        }

        (new RequestLifecycle())->accept($other, $runner);
        $this->assertFalse((new ChatPolicy())->canSend($runner, $requester)['allowed']);
    }

    // ---------------------------------------------------------------- pages

    public function testOverviewShowsChartsAttentionAndEscapedRecentRequests(): void
    {
        $admin = $this->createUser(['role' => 'admin']);
        $this->createRequest($this->createUser(), ['item_name' => '<script>x</script>']);

        $response = $this->getAs($admin, 'admin/dashboard', 'admin');
        $response->assertOK();
        $response->assertSee('&lt;script&gt;x&lt;/script&gt;', null);
        $response->assertSee('Requests posted');
        $response->assertSee('Needs attention');
        $this->assertSame(24, substr_count($response->getBody(), 'class="bar-col"'), 'Two charts × 12 periods.');

        $this->getAs($admin, 'admin/dashboard?range=12m', 'admin')->assertSee('in 12 months');
        $this->assertRedirectsTo($this->getAs($admin, 'admin/dashboard?tab=delivered', 'admin'), 'admin/requests?status=delivered');
    }

    public function testRequestsPageFiltersByStatusAndSearch(): void
    {
        $admin = $this->createUser(['role' => 'admin']);
        $a     = $this->createRequest($this->createUser(['first_name' => 'Lorna']), ['item_name' => 'Rice sack']);
        $this->createRequest($this->createUser(), ['item_name' => 'Soap bar']);
        (new RequestLifecycle())->cancel($a, $admin, true);

        $page = $this->getAs($admin, 'admin/requests?status=cancelled', 'admin');
        $page->assertSee('Rice sack');
        $page->assertDontSee('Soap bar');

        $byName = $this->getAs($admin, 'admin/requests?q=Lorna', 'admin');
        $byName->assertSee('Rice sack');
        $byName->assertDontSee('Soap bar');

        $this->getAs($admin, 'admin/requests?q=%23' . $a, 'admin')->assertSee('Rice sack');
        $this->getAs($admin, 'admin/requests?status=bogus', 'admin')->assertSee('Soap bar'); // unknown filter ignored
    }

    public function testCsvExportUsesTheFiltersAndNeutralisesFormulas(): void
    {
        $admin = $this->createUser(['role' => 'admin']);
        $this->createRequest($this->createUser(), ['item_name' => '=HYPERLINK("http://evil.test")']);
        $this->createRequest($this->createUser(), ['item_name' => 'Plain item']);

        $response = $this->getAs($admin, 'admin/requests/export?q=HYPERLINK', 'admin');
        $response->assertOK();
        $this->assertStringContainsString('text/csv', $response->response()->getHeaderLine('Content-Type'));
        $this->assertStringContainsString('attachment;', $response->response()->getHeaderLine('Content-Disposition'));

        $csv = $response->getBody();
        $this->assertStringContainsString("'=HYPERLINK", $csv, 'Formula is prefixed so spreadsheets show it as text.');
        $this->assertStringNotContainsString('Plain item', $csv);

        $this->assertRedirectsTo($this->getAs($this->createUser(), 'admin/requests/export'), 'dashboard');
    }

    public function testUsersPageSearchesAndFlagsLedgerMismatches(): void
    {
        $admin = $this->createUser(['role' => 'admin']);
        $ok    = $this->createUser(['first_name' => 'Balanced']);
        $off   = $this->createUser(['first_name' => 'Drifted']);
        $this->fund($ok, '100');
        $this->db->table('users')->where('id', $off)->update(['wallet_balance' => '50.00']); // no ledger entry

        $page = $this->getAs($admin, 'admin/users?q=Drifted', 'admin');
        $page->assertSee('Drifted');
        $page->assertDontSee('Balanced');

        $mismatch = $this->getAs($admin, 'admin/users?check=ledger', 'admin');
        $mismatch->assertSee('Drifted');
        $mismatch->assertDontSee('Balanced');
        $mismatch->assertSee('Ledger:');
    }

    public function testTopupsPageSeparatesPaidFromPending(): void
    {
        $admin  = $this->createUser(['role' => 'admin']);
        $member = $this->createUser(['first_name' => 'Topper']);
        $this->db->table('wallet_topups')->insertBatch([
            ['user_id' => $member, 'amount' => '100.00', 'provider' => 'xendit', 'status' => 'pending', 'livemode' => 0, 'created_at' => date('Y-m-d H:i:s', time() - 3600), 'paid_at' => null],
            ['user_id' => $member, 'amount' => '200.00', 'provider' => 'xendit', 'status' => 'paid', 'livemode' => 0, 'created_at' => date('Y-m-d H:i:s'), 'paid_at' => date('Y-m-d H:i:s')],
        ]);

        $page = $this->getAs($admin, 'admin/topups', 'admin');
        $page->assertOK();
        $page->assertSee('Topper');
        $page->assertSee('Likely abandoned');
        $page->assertSee('No money received');

        $paid = $this->getAs($admin, 'admin/topups?status=paid', 'admin');
        $paid->assertDontSee('Likely abandoned');
    }

    public function testAdminActionsOnlyReturnToAdminPages(): void
    {
        $admin  = $this->createUser(['role' => 'admin']);
        $member = $this->createUser();

        $this->assertRedirectsTo($this->suspend($admin, $member, ['return' => 'admin/users?q=x']), 'admin/users?q=x');
        $this->assertRedirectsTo(
            $this->postAs($admin, 'admin/users/' . $member . '/restore', ['return' => 'https://evil.test/admin/users'], 'admin'),
            'admin/users',
        );
    }
}

<?php

use App\Services\RequestLifecycle;
use Tests\Support\FeatureTestCase;

/**
 * @internal
 */
final class ProfileAndNavigationTest extends FeatureTestCase
{
    public function testProfileShowsEverythingEnteredAtRegistration(): void
    {
        $user = $this->createUser([
            'first_name' => 'Liza', 'last_name' => 'Santos', 'email' => 'liza@example.test',
            'phone_number' => '09181234567', 'address' => 'Purok 5, Barangay Uno', 'username' => 'liza.s',
        ]);

        $page = $this->getAs($user, 'profile');

        $page->assertOK();
        foreach (['Liza', 'Santos', 'liza@example.test', '09181234567', 'Purok 5, Barangay Uno', '@liza.s', 'Member since'] as $text) {
            $page->assertSee($text);
        }
    }

    public function testProfileEditUpdatesMobileAndAddress(): void
    {
        $user = $this->createUser(['phone_number' => '09180000001']);

        $response = $this->postAs($user, 'profile/update', [
            'first_name' => 'New', 'last_name' => 'Name', 'username' => 'new.name',
            'phone_number' => '0918 000 0002', 'address' => 'New Street 9', 'about_me' => 'Hello',
        ]);

        $this->assertRedirectsTo($response, 'profile');
        $row = $this->db->table('users')->where('id', $user)->get()->getRowArray();
        $this->assertSame('09180000002', $row['phone_number']);
        $this->assertSame('New Street 9', $row['address']);
        $this->assertSame('New', $row['first_name']);
    }

    public function testProfileEditRejectsAnotherUsersMobileAndBadInput(): void
    {
        $this->createUser(['phone_number' => '09189999999']);
        $user = $this->createUser(['phone_number' => '09180000001', 'email' => 'keep@example.test']);

        $this->postAs($user, 'profile/update', [
            'first_name' => '', 'last_name' => 'X', 'phone_number' => '09189999999', 'address' => '',
            'email' => 'hijack@example.test',
        ]);

        $errors = $this->flash('errors');
        $this->assertArrayHasKey('phone_number', $errors);
        $this->assertArrayHasKey('first_name', $errors);
        $this->assertArrayHasKey('address', $errors);

        $row = $this->db->table('users')->where('id', $user)->get()->getRowArray();
        $this->assertSame('09180000001', $row['phone_number']);
        $this->assertSame('keep@example.test', $row['email'], 'Email cannot be changed from the profile form.');
    }

    public function testKeepingYourOwnMobileNumberIsAllowed(): void
    {
        $user = $this->createUser(['phone_number' => '09180000001']);

        $this->postAs($user, 'profile/update', ['first_name' => 'A', 'last_name' => 'B', 'phone_number' => '09180000001', 'address' => 'Here']);

        $this->assertNull($this->flash('errors'));
        $this->assertSame('Profile updated.', $this->flash('success'));
    }

    public function testChangePasswordRequiresCurrentPassword(): void
    {
        $user = $this->createUser();

        $this->postAs($user, 'profile/password', ['current_password' => 'wrong', 'new_password' => 'brandnew123', 'confirm_password' => 'brandnew123']);

        $this->assertArrayHasKey('current_password', $this->flash('errors'));
        $hash = $this->db->table('users')->select('password')->where('id', $user)->get()->getRow()->password;
        $this->assertTrue(password_verify(self::PASSWORD, $hash));
    }

    public function testChangePasswordValidatesAndThenWorksForLogin(): void
    {
        $user  = $this->createUser(['email' => 'pw@example.test']);

        $this->postAs($user, 'profile/password', ['current_password' => self::PASSWORD, 'new_password' => 'short', 'confirm_password' => 'other']);
        $errors = $this->flash('errors');
        $this->assertArrayHasKey('new_password', $errors);
        $this->assertArrayHasKey('confirm_password', $errors);

        $this->postAs($user, 'profile/password', ['current_password' => self::PASSWORD, 'new_password' => 'brandnew123', 'confirm_password' => 'brandnew123']);
        $this->assertStringContainsString('Password changed', (string) $this->flash('success'));

        $this->assertRedirectsTo($this->postAs(null, 'login/process', ['email' => 'pw@example.test', 'password' => 'brandnew123']), 'dashboard');
    }

    public function testDashboardTellsRequesterToPayAndRunnerToStart(): void
    {
        $requester = $this->createUser();
        $runner    = $this->createUser();
        $requestId = $this->createRequest($requester, ['item_name' => 'Cooking oil']);
        (new RequestLifecycle())->accept($requestId, $runner);

        $requesterDash = $this->getAs($requester, 'dashboard');
        $requesterDash->assertSee('Needs your attention');
        $requesterDash->assertSee('Pay your runner for Cooking oil');

        $runnerDash = $this->getAs($runner, 'dashboard');
        $runnerDash->assertSee('Start delivering Cooking oil');
    }

    public function testRequestDetailsExplainsTheNextStepForEachPerson(): void
    {
        $requester = $this->createUser();
        $runner    = $this->createUser();
        $requestId = $this->createRequest($requester);

        $this->getAs($runner, 'request-details/' . $requestId)->assertSee('Want to do this errand?');
        $this->getAs($requester, 'request-details/' . $requestId)->assertSee('Waiting for a runner');

        (new RequestLifecycle())->accept($requestId, $runner);

        $this->getAs($requester, 'request-details/' . $requestId)->assertSee('Pay your runner');
        $this->getAs($runner, 'request-details/' . $requestId)->assertSee('Buy the items, then set off');
    }

    public function testNotificationDropdownFeedReturnsOnlyMyLatestItemsAsPlainData(): void
    {
        $me    = $this->createUser();
        $other = $this->createUser();
        $req   = $this->createRequest($this->createUser());

        $this->db->table('request_notifications')->insertBatch([
            ['user_id' => $me, 'request_id' => $req, 'type' => 'delivered', 'title' => '<b>Delivered</b>', 'content' => 'Mine', 'is_read' => 0],
            ['user_id' => $other, 'request_id' => $req, 'type' => 'delivered', 'title' => 'Not mine', 'content' => 'Other', 'is_read' => 0],
        ]);

        $data = json_decode($this->getAs($me, 'notifications/feed')->getJSON(), true);

        $this->assertSame(1, $data['unread']);
        $this->assertCount(1, $data['items']);
        $this->assertSame('<b>Delivered</b>', $data['items'][0]['title'], 'Raw text; the browser renders it with textContent.');
        $this->assertTrue($data['items'][0]['unread']);
        $this->assertStringContainsString('notifications/open/', $data['items'][0]['url']);
    }

    public function testMarkAllReadFromTheDropdownStaysOnThePage(): void
    {
        $me    = $this->createUser();
        $other = $this->createUser();
        $req   = $this->createRequest($this->createUser());

        $this->db->table('request_notifications')->insertBatch([
            ['user_id' => $me, 'request_id' => $req, 'type' => 'delivered', 'title' => 'A', 'content' => 'A', 'is_read' => 0],
            ['user_id' => $other, 'request_id' => $req, 'type' => 'delivered', 'title' => 'B', 'content' => 'B', 'is_read' => 0],
        ]);

        // Background request from the dropdown: JSON, no redirect.
        $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])->postAs($me, 'notifications/mark-all-read');

        $response->assertOK();
        $this->assertFalse($response->isRedirect());
        $this->assertSame(['ok' => true, 'notifications' => 0], json_decode($response->getJSON(), true));
        $this->assertSame(0, $this->countRows('request_notifications', ['user_id' => $me, 'is_read' => 0]));
        $this->assertSame(1, $this->countRows('request_notifications', ['user_id' => $other, 'is_read' => 0]), "Other users' notifications are untouched.");
    }

    public function testMarkAllReadWithoutJavaScriptDoesNotJumpToNotificationsPage(): void
    {
        $me = $this->createUser();

        $response = $this->withHeaders(['Referer' => base_url('wallet')])->postAs($me, 'notifications/mark-all-read');

        $this->assertTrue($response->isRedirect());
        $this->assertStringNotContainsString('/notifications', $response->getRedirectUrl());
    }

    public function testNavigationShowsGroupedMenuAndBreadcrumbs(): void
    {
        $user = $this->createUser();

        $page = $this->getAs($user, 'my-requests');

        $page->assertSee('I need something');
        $page->assertSee('I want to earn');
        $page->assertSee('My deliveries');
        $page->assertSee('aria-label="Breadcrumb"', null);
        $page->assertSee('class="side-link is-active" aria-current="page"', null);
    }
}

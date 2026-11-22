<?php

use Tests\Support\FeatureTestCase;

/**
 * @internal
 */
final class AuthAndSecurityTest extends FeatureTestCase
{
    // ------------------------------------------------------- registration

    public function testRegistrationCreatesAnOrdinaryUserEvenIfAdminRoleIsPosted(): void
    {
        $response = $this->postAs(null, 'register/process', [
            'first_name' => 'Nina', 'last_name' => 'New', 'email' => 'Nina@Example.test',
            'phone' => '0917 123 4567', 'address' => 'Somewhere', 'role' => 'admin',
            'password' => 'longenough1', 'confirm_password' => 'longenough1',
        ]);

        $this->assertRedirectsTo($response, 'login');
        $user = $this->db->table('users')->where('email', 'nina@example.test')->get()->getRowArray();
        $this->assertNotNull($user, 'Email is normalised to lower case.');
        $this->assertSame('user', $user['role']);
        $this->assertSame('09171234567', $user['phone_number']);
        $this->assertTrue(password_verify('longenough1', $user['password']));
    }

    public function testRegistrationValidatesTheRawPasswordAndKeepsSafeInput(): void
    {
        $response = $this->postAs(null, 'register/process', [
            'first_name' => 'Nina', 'last_name' => 'New', 'email' => 'nina@example.test',
            'phone' => '09171234567', 'address' => 'Somewhere',
            'password' => 'short', 'confirm_password' => 'short',
        ]);

        $this->assertTrue($response->isRedirect());
        $this->assertSame(0, $this->countRows('users'));
        $this->assertArrayHasKey('password', $this->flash('errors'));
        $this->assertSame('nina@example.test', $this->flash('form_old')['email']);
        $this->assertArrayNotHasKey('password', $this->flash('form_old'), 'Passwords are never flashed back.');
    }

    public function testRegistrationRejectsDuplicateEmailAndBadPhone(): void
    {
        $this->createUser(['email' => 'taken@example.test']);

        $this->postAs(null, 'register/process', [
            'first_name' => 'A', 'last_name' => 'B', 'email' => 'taken@example.test', 'phone' => '12345',
            'address' => 'X', 'password' => 'longenough1', 'confirm_password' => 'longenough1',
        ]);

        $errors = $this->flash('errors');
        $this->assertArrayHasKey('email', $errors);
        $this->assertArrayHasKey('phone', $errors);
    }

    // -------------------------------------------------------------- login

    public function testInvalidLoginShowsAGenericError(): void
    {
        $this->createUser(['email' => 'real@example.test']);

        foreach (['real@example.test', 'nobody@example.test'] as $email) {
            $response = $this->postAs(null, 'login/process', ['email' => $email, 'password' => 'wrong-password']);

            $this->assertTrue($response->isRedirect());
            $this->assertSame('Invalid email or password.', $this->flash('error'));
            $this->assertArrayNotHasKey('user_id', $_SESSION);
        }
    }

    public function testSuccessfulLoginStoresRoleAndRedirectsByRole(): void
    {
        $userId  = $this->createUser(['email' => 'user@example.test']);
        $adminId = $this->createUser(['email' => 'admin@example.test', 'role' => 'admin']);

        $response = $this->postAs(null, 'login/process', ['email' => 'user@example.test', 'password' => self::PASSWORD]);
        $this->assertRedirectsTo($response, 'dashboard');
        $this->assertSame($userId, $_SESSION['user_id']);
        $this->assertSame('user', $_SESSION['role']);
        $this->assertTrue($_SESSION['isLoggedIn']);

        $response = $this->postAs(null, 'login/process', ['email' => 'ADMIN@example.test', 'password' => self::PASSWORD]);
        $this->assertRedirectsTo($response, 'admin/dashboard');
        $this->assertSame($adminId, $_SESSION['user_id']);
        $this->assertSame('admin', $_SESSION['role']);
    }

    public function testLoginIsThrottledAfterRepeatedFailures(): void
    {
        $this->createUser(['email' => 'target@example.test']);

        for ($i = 0; $i < 5; $i++) {
            $this->postAs(null, 'login/process', ['email' => 'target@example.test', 'password' => 'guess' . $i]);
        }

        // Even the correct password is refused while throttled.
        $response = $this->postAs(null, 'login/process', ['email' => 'target@example.test', 'password' => self::PASSWORD]);

        $this->assertTrue($response->isRedirect());
        $this->assertStringContainsString('Too many failed attempts', (string) $this->flash('error'));
        $this->assertArrayNotHasKey('user_id', $_SESSION);
    }

    public function testLogoutRequiresPost(): void
    {
        $userId = $this->createUser();

        $this->assertNotFound(fn () => $this->getAs($userId, 'logout'));

        $response = $this->postAs($userId, 'logout');
        $this->assertRedirectsTo($response, 'login');
    }

    // --------------------------------------------------------------- CSRF

    public function testStateChangingRequestsWithoutCsrfTokenAreRejected(): void
    {
        $requester = $this->createUser();
        $runner    = $this->createUser();
        $requestId = $this->createRequest($requester);

        $response = $this->postAs($runner, 'request/accept/' . $requestId, [], 'user', false);

        $this->assertTrue($response->isRedirect());
        $this->assertSame('The action you requested is not allowed.', $this->flash('error'));
        $this->assertSame('pending', $this->requestRow($requestId)['status']);
    }

    public function testForgedCsrfTokenIsRejected(): void
    {
        $user = $this->createUser();

        $this->postAs($user, 'wallet/top-up', [
            'amount' => '100', 'idempotency_key' => str_repeat('a', 32),
            'csrf_test_name' => bin2hex(random_bytes(32)),
        ], 'user', false);

        $this->assertSame('0.00', $this->balance($user));
    }

    // ------------------------------------------------------ authorization

    public function testGuestsAreRedirectedFromEveryProtectedPage(): void
    {
        foreach (['dashboard', 'wallet', 'messages', 'my-requests', 'browse-requests', 'profile', 'notifications', 'admin/dashboard', 'media/avatar/x.jpg'] as $path) {
            $response = $this->getAs(null, $path);
            $this->assertRedirectsTo($response, 'login');
        }
    }

    public function testGuestsCannotPerformActions(): void
    {
        $requestId = $this->createRequest($this->createUser());

        $response = $this->postAs(null, 'request/accept/' . $requestId);

        $this->assertRedirectsTo($response, 'login');
        $this->assertSame('pending', $this->requestRow($requestId)['status']);
    }

    public function testNonAdminsCannotReachAdminPagesOrActions(): void
    {
        $user      = $this->createUser();
        $requestId = $this->createRequest($this->createUser());

        $this->assertRedirectsTo($this->getAs($user, 'admin/dashboard'), 'dashboard');

        // A forged session role is not trusted: the filter re-checks the DB.
        $this->assertRedirectsTo($this->getAs($user, 'admin/dashboard', 'admin'), 'dashboard');

        $this->postAs($user, 'admin/request/cancel/' . $requestId, [], 'admin');
        $this->assertSame('pending', $this->requestRow($requestId)['status']);
    }

    public function testAdminCanOpenDashboardAndCancelUnpaidRequest(): void
    {
        $admin     = $this->createUser(['role' => 'admin']);
        $requestId = $this->createRequest($this->createUser(), ['item_name' => 'Visible <b>item</b>']);

        $response = $this->getAs($admin, 'admin/dashboard', 'admin');
        $response->assertOK();
        $response->assertSee('Visible &lt;b&gt;item&lt;/b&gt;', null);

        $this->assertRedirectsTo($this->postAs($admin, 'admin/request/cancel/' . $requestId, [], 'admin'), 'admin/dashboard');
        $this->assertSame('cancelled', $this->requestRow($requestId)['status']);
        $this->assertSame(1, $this->countRows('requests'), 'Requests are cancelled, never deleted.');
    }

    public function testUsersCannotPayForSomeoneElsesRequest(): void
    {
        $requester = $this->createUser();
        $attacker  = $this->createUser();
        $this->fund($attacker, '1000');
        $requestId = $this->createRequest($requester);
        (new App\Services\RequestLifecycle())->accept($requestId, $this->createUser());

        $this->postAs($attacker, 'request/pay-runner/' . $requestId);

        $this->assertSame('1000.00', $this->balance($attacker));
        $this->assertSame('unpaid', $this->requestRow($requestId)['payment_status']);
    }

    public function testOutsidersCannotViewAcceptedRequestsAndDoNotSeeContactDetails(): void
    {
        $requester = $this->createUser();
        $runner    = $this->createUser();
        $outsider  = $this->createUser();
        $requestId = $this->createRequest($requester, ['delivery_address' => 'Secret Lane 7', 'contact_number' => '09998887777']);

        $pending = $this->getAs($outsider, 'request-details/' . $requestId);
        $pending->assertOK();
        $pending->assertDontSee('Secret Lane 7');
        $pending->assertDontSee('09998887777');

        (new App\Services\RequestLifecycle())->accept($requestId, $runner);

        $this->assertRedirectsTo($this->getAs($outsider, 'request-details/' . $requestId), 'browse-requests');

        $asRunner = $this->getAs($runner, 'request-details/' . $requestId);
        $asRunner->assertOK();
        $asRunner->assertSee('Secret Lane 7');
    }

    public function testUserSearchDoesNotExposeEmails(): void
    {
        $viewer = $this->createUser();
        $this->createUser(['first_name' => 'Searchable', 'email' => 'private.address@example.test']);

        $response = $this->getAs($viewer, 'users/search?q=Searchable');

        $response->assertOK();
        $response->assertSee('Searchable');
        $response->assertDontSee('private.address@example.test');
        $this->getAs($viewer, 'users/search?q=private.address')->assertDontSee('Searchable');
    }

    public function testMessageAttachmentsAreOnlyServedToParticipants(): void
    {
        $sender   = $this->createUser();
        $receiver = $this->createUser();
        $outsider = $this->createUser();

        $name = bin2hex(random_bytes(16)) . '.pdf';
        $dir  = App\Libraries\UploadStorage::directory(App\Libraries\UploadStorage::MESSAGE_FILES);
        is_dir($dir) || mkdir($dir, 0755, true);
        file_put_contents($dir . $name, "%PDF-1.4\n%%EOF");

        $this->db->table('messages')->insert([
            'sender_id' => $sender, 'receiver_id' => $receiver, 'message' => '',
            'attachment_name' => 'receipt.pdf', 'attachment_path' => 'message_attachments/' . $name,
            'attachment_type' => 'application/pdf',
        ]);
        $messageId = (int) $this->db->insertID();

        try {
            $ok = $this->getAs($receiver, 'messages/attachment/' . $messageId);
            $ok->assertOK();
            $this->assertSame('application/pdf', $ok->response()->getHeaderLine('Content-Type'));
            $this->assertSame('nosniff', $ok->response()->getHeaderLine('X-Content-Type-Options'));

            $this->assertNotFound(fn () => $this->getAs($outsider, 'messages/attachment/' . $messageId));
        } finally {
            @unlink($dir . $name);
        }
    }

    public function testMessagesTiedToARequestMustBeBetweenItsParticipants(): void
    {
        $requester = $this->createUser();
        $runner    = $this->createUser();
        $outsider  = $this->createUser();
        $requestId = $this->createRequest($requester);
        (new App\Services\RequestLifecycle())->accept($requestId, $runner);

        $this->postAs($outsider, 'messages/send', ['receiver_id' => $requester, 'request_id' => $requestId, 'message' => 'Hi']);
        $this->assertSame(0, $this->countRows('messages'));

        $this->postAs($runner, 'messages/send', ['receiver_id' => $requester, 'request_id' => $requestId, 'message' => 'On my way']);
        $this->assertSame(1, $this->countRows('messages'));
        $this->assertSame(1, $this->countRows('notifications', ['user_id' => $requester, 'type' => 'message']));
    }

    public function testRatingsRequireACompletedErrandTogether(): void
    {
        $requester = $this->createUser();
        $runner    = $this->createUser();
        $requestId = $this->createRequest($requester);
        (new \App\Services\RequestLifecycle())->accept($requestId, $runner);

        // Accepted but not delivered yet.
        $this->postAs($requester, 'request/' . $requestId . '/rate', ['rating' => 5]);
        $this->assertSame(0, $this->countRows('user_ratings'));
        $this->assertStringContainsString('delivered', (string) $this->flash('error'));
    }

    public function testHealthEndpointReportsOk(): void
    {
        $response = $this->getAs(null, 'health');

        $response->assertOK();
        $this->assertSame('ok', json_decode($response->getJSON(), true)['status']);
    }
}

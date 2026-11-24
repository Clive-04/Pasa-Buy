<?php

use Tests\Support\FeatureTestCase;

/**
 * Complete requester → runner → admin journeys through HTTP (routing,
 * filters, CSRF, controllers, services, views), with synthetic data.
 *
 * @internal
 */
final class UserJourneyTest extends FeatureTestCase
{
    private function requestForm(array $overrides = []): array
    {
        return $overrides + [
            'item_name' => 'Paracetamol 500mg', 'category' => 'medicine', 'quantity' => '2',
            'brand' => '', 'item_notes' => 'Generic is fine', 'store_name' => 'Demo Pharmacy', 'alt_store' => '',
            'store_address' => 'Market Road', 'delivery_address' => 'Purok 3, Sample Town', 'contact_number' => '0917-000-1111',
            'landmark' => 'Blue gate', 'delivery_notes' => '', 'estimated_price' => '95.50', 'service_fee' => '45',
            'payment_method' => 'wallet', 'urgency' => 'urgent',
        ];
    }

    public function testWalletJourneyFromPostingToAdminReport(): void
    {
        $requester = $this->createUser(['first_name' => 'Maria']);
        $runner    = $this->createUser(['first_name' => 'Jose']);
        $admin     = $this->createUser(['role' => 'admin']);

        // Requester adds demo credits (double-submitting the same form).
        $key = bin2hex(random_bytes(16));
        $this->assertRedirectsTo($this->postAs($requester, 'wallet/top-up', ['amount' => '500', 'idempotency_key' => $key]), 'wallet');
        $this->postAs($requester, 'wallet/top-up', ['amount' => '500', 'idempotency_key' => $key]);
        $this->assertStringContainsString('already processed', (string) $this->flash('success'));
        $this->assertSame('500.00', $this->balance($requester));

        // Requester posts a request.
        $this->assertRedirectsTo($this->postAs($requester, 'request/process', $this->requestForm()), 'my-requests');
        $requestId = (int) $this->db->table('requests')->select('id')->get()->getRow()->id;
        $this->assertSame('140.50', $this->requestRow($requestId)['total_amount']);
        $this->getAs($requester, 'my-requests')->assertSee('Paracetamol 500mg');

        // Runner finds and accepts it.
        $this->getAs($runner, 'browse-requests')->assertSee('Paracetamol 500mg');
        $this->assertRedirectsTo($this->postAs($runner, 'request/accept/' . $requestId), 'accepted-requests');
        $this->getAs($runner, 'accepted-requests')->assertSee('You receive');

        // They message each other about the request.
        $this->postAs($runner, 'messages/send', ['receiver_id' => $requester, 'request_id' => $requestId, 'message' => 'At the pharmacy now']);
        $this->getAs($requester, 'messages?user_id=' . $runner . '&request_id=' . $requestId)->assertSee('At the pharmacy now');

        // Runner cannot complete before payment.
        $this->postAs($runner, 'request/status/' . $requestId, ['status' => 'on_the_way']);
        $this->postAs($runner, 'request/status/' . $requestId, ['status' => 'delivered']);
        $this->assertSame('on_the_way', $this->requestRow($requestId)['status']);

        // R5: no payment until the actual price + receipt is approved.
        $this->postAs($requester, 'request/pay-runner/' . $requestId);
        $this->assertStringContainsString('actual price', (string) $this->flash('error'));
        $this->assertSame('500.00', $this->balance($requester));
        $this->approvePrice($requestId);

        // Requester pays from the wallet.
        $this->postAs($requester, 'request/pay-runner/' . $requestId);
        $this->assertSame('Payment sent to the runner from your wallet.', $this->flash('success'));
        $this->assertSame('359.50', $this->balance($requester));
        $this->assertSame('136.00', $this->balance($runner));

        // Runner completes the delivery.
        $this->postAs($runner, 'request/status/' . $requestId, ['status' => 'delivered']);
        $this->assertSame('delivered', $this->requestRow($requestId)['status']);

        // Histories agree.
        $wallet = $this->getAs($runner, 'wallet');
        $wallet->assertSee('Platform fee');
        $wallet->assertSee('₱136.00');

        $notifications = $this->getAs($requester, 'notifications');
        $notifications->assertSee('Your request was accepted');
        $notifications->assertSee('Your request was delivered');

        // Requester can now rate the runner.
        $this->getAs($requester, 'request-details/' . $requestId)->assertSee('Rate runner');
        $this->postAs($requester, 'request/' . $requestId . '/rate', ['rating' => '5', 'feedback' => 'Quick!']);
        $this->assertSame(1, $this->countRows('user_ratings', ['rated_user_id' => $runner, 'request_id' => $requestId]));

        // Admin report reflects recorded collections and reconciles.
        $report = $this->getAs($admin, 'admin/dashboard', 'admin');
        $report->assertOK();
        $report->assertSee('₱4.50');
        $report->assertSee('Balanced');
        $report->assertDontSee('differs from its ledger');
        $report->assertDontSee('differ from their ledgers');

        $delivered = $this->getAs($admin, 'admin/requests?status=delivered', 'admin');
        $delivered->assertSee('#' . $requestId);
        $this->getAs($admin, 'admin/users?check=ledger', 'admin')->assertSee('0 accounts match');
    }

    public function testPostRequestPrefillsDeliveryDetailsButKeepsThemEditable(): void
    {
        $user = $this->createUser(['address' => 'Purok 5, Barangay Mabini', 'phone_number' => '09175551234']);

        $form = $this->getAs($user, 'post-request');
        $form->assertSee('value="Purok 5, Barangay Mabini"', null);
        $form->assertSee('value="09175551234"', null);
        $form->assertSee('From your profile');
        $form->assertDontSee('id="delivery_address" readonly', null);

        // A different address typed in for this request is the one saved.
        $this->postAs($user, 'request/process', $this->requestForm(['delivery_address' => 'Office, 2nd floor', 'contact_number' => '09170001111']));
        $row = $this->db->table('requests')->where('user_id', $user)->get()->getRowArray();
        $this->assertSame('Office, 2nd floor', $row['delivery_address']);
        $this->assertSame('Purok 5, Barangay Mabini', $this->db->table('users')->where('id', $user)->get()->getRow()->address, 'The profile itself is unchanged.');
    }

    public function testCashOnDeliveryJourney(): void
    {
        $requester = $this->createUser();
        $runner    = $this->createUser();
        $admin     = $this->createUser(['role' => 'admin']);

        $this->postAs($requester, 'request/process', $this->requestForm(['payment_method' => 'cod', 'estimated_price' => '180', 'service_fee' => '50']));
        $requestId = (int) $this->db->table('requests')->select('id')->get()->getRow()->id;

        // Runner without credits is told to top up first.
        $this->postAs($runner, 'request/accept/' . $requestId);
        $this->assertStringContainsString('₱5.00', (string) $this->flash('error'));

        $this->postAs($runner, 'wallet/top-up', ['amount' => '20', 'idempotency_key' => bin2hex(random_bytes(16))]);
        $this->postAs($runner, 'request/accept/' . $requestId);
        $this->postAs($runner, 'request/status/' . $requestId, ['status' => 'on_the_way']);

        // R5: cash can't be settled until the requester approves the actual price.
        $this->postAs($runner, 'request/status/' . $requestId, ['status' => 'delivered', 'cod_confirmed' => '1']);
        $this->assertSame('cod_pending', $this->requestRow($requestId)['payment_status']);
        $this->assertStringContainsString('approve the actual price', (string) $this->flash('error'));
        $this->approvePrice($requestId);

        // Requester sees what to pay in cash; no pay button.
        $details = $this->getAs($requester, 'request-details/' . $requestId);
        $details->assertSee('Pay the runner ₱230.00 in cash');
        $details->assertDontSee('payRunnerDialog');

        // Delivery without confirming cash is refused; with confirmation it settles.
        $this->postAs($runner, 'request/status/' . $requestId, ['status' => 'delivered']);
        $this->assertSame('on_the_way', $this->requestRow($requestId)['status']);

        $this->postAs($runner, 'request/status/' . $requestId, ['status' => 'delivered', 'cod_confirmed' => '1']);
        $this->assertSame('paid_cod', $this->requestRow($requestId)['payment_status']);
        $this->assertSame('15.00', $this->balance($runner));
        $this->assertSame('0.00', $this->balance($requester));

        $report = $this->getAs($admin, 'admin/dashboard', 'admin');
        $report->assertSee('₱5.00');
        $report->assertSee('Balanced');
    }

    public function testFailedPostKeepsTheFormInput(): void
    {
        $requester = $this->createUser();

        $this->postAs($requester, 'request/process', $this->requestForm(['contact_number' => 'not-a-phone', 'estimated_price' => '-4']));

        $this->assertSame(0, $this->countRows('requests'));
        $errors = $this->flash('errors');
        $this->assertArrayHasKey('contact_number', $errors);
        $this->assertArrayHasKey('estimated_price', $errors);
        $this->assertSame('Paracetamol 500mg', $_SESSION['_ci_old_input']['post']['item_name'] ?? null);
    }

    public function testRequesterCanCancelPendingRequestFromMyRequests(): void
    {
        $requester = $this->createUser();
        $requestId = $this->createRequest($requester);

        $this->assertRedirectsTo($this->postAs($requester, 'request/cancel/' . $requestId), 'my-requests');
        $this->assertSame('cancelled', $this->requestRow($requestId)['status']);
        $this->getAs($this->createUser(), 'browse-requests')->assertDontSee('Test item');
    }

    public function testReferralMessageDoesNotClaimAnEmailWasSent(): void
    {
        $user = $this->createUser();

        $this->postAs($user, 'profile/refer-friend', ['friend_name' => 'Pat', 'friend_email' => 'pat@example.test']);

        $this->assertSame(1, $this->countRows('referrals', ['user_id' => $user]));
        $this->assertStringContainsString('does not send', (string) $this->flash('success'));
        $this->assertStringNotContainsStringIgnoringCase('sent', str_replace('does not send', '', (string) $this->flash('success')));
    }

    public function testProfileShowsRealRatingsInsteadOfAFixedFiveStars(): void
    {
        $user = $this->createUser();

        $this->getAs($user, 'profile')->assertSee('No ratings yet');

        $this->db->table('user_ratings')->insertBatch([
            ['rater_id' => $this->createUser(), 'rated_user_id' => $user, 'rating' => 4],
            ['rater_id' => $this->createUser(), 'rated_user_id' => $user, 'rating' => 3],
        ]);

        $profile = $this->getAs($user, 'profile');
        $profile->assertSee('3.5');
        $profile->assertSee('3.5 from 2 ratings');
    }

    public function testPagesNoLongerAutoRefresh(): void
    {
        $user = $this->createUser();

        foreach (['dashboard', 'post-request', 'messages', 'wallet'] as $path) {
            $page = $this->getAs($user, $path);
            $page->assertOK();
            $page->assertDontSee('http-equiv="refresh"');
            $page->assertDontSee('location.reload');
        }

        $this->getAs($this->createUser(['role' => 'admin']), 'admin/dashboard', 'admin')->assertDontSee('location.reload');
    }

    public function testUnreadCountEndpointReturnsJson(): void
    {
        $user = $this->createUser();
        $this->db->table('request_notifications')->insert([
            'user_id' => $user, 'request_id' => $this->createRequest($this->createUser()), 'type' => 't', 'title' => 't', 'content' => 'c',
        ]);

        $response = $this->getAs($user, 'notifications/unread-count');

        $response->assertOK();
        $this->assertSame(['notifications' => 1, 'messages' => 0], json_decode($response->getJSON(), true));
    }
}

<?php

use App\Services\RequestLifecycle;
use App\Services\ServiceException;
use App\Services\WalletService;
use Tests\Support\DatabaseTestCase;

/**
 * @internal
 */
final class RequestLifecycleTest extends DatabaseTestCase
{
    private RequestLifecycle $lifecycle;
    private WalletService $wallet;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lifecycle = new RequestLifecycle();
        $this->wallet    = new WalletService();
    }

    public function testCreateStoresApprovedAmounts(): void
    {
        $requester = $this->createUser();
        $id        = $this->createRequest($requester, ['estimated_price' => '95.50', 'service_fee' => '45']);
        $row       = $this->requestRow($id);

        $this->assertSame('pending', $row['status']);
        $this->assertSame('unpaid', $row['payment_status']);
        $this->assertSame('140.50', $row['total_amount']);
        $this->assertSame('4.50', $row['platform_fee']);
    }

    public function testCodRequestStartsCashPending(): void
    {
        $id = $this->createRequest($this->createUser(), ['payment_method' => 'cod']);

        $this->assertSame('cod_pending', $this->requestRow($id)['payment_status']);
    }

    public function testRejectsLegacyOrUnknownPaymentMethods(): void
    {
        $this->expectException(ServiceException::class);
        $this->createRequest($this->createUser(), ['payment_method' => 'gcash']);
    }

    public function testRejectsNegativeAndMalformedAmounts(): void
    {
        foreach (['-5', '1.234', 'abc'] as $bad) {
            try {
                $this->createRequest($this->createUser(), ['estimated_price' => $bad]);
                $this->fail("Accepted invalid amount {$bad}");
            } catch (ServiceException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(0, $this->countRows('requests'));
    }

    public function testRunnerCanAcceptOnceAndRequesterIsNotified(): void
    {
        $requester = $this->createUser();
        $runner    = $this->createUser();
        $id        = $this->createRequest($requester);

        $this->lifecycle->accept($id, $runner);

        $this->assertSame('accepted', $this->requestRow($id)['status']);
        $this->assertSame(1, $this->countRows('accepted_requests', ['request_id' => $id, 'runner_id' => $runner]));
        $this->assertSame(1, $this->countRows('request_notifications', ['user_id' => $requester, 'type' => 'request_accepted']));

        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('no longer available');
        $this->lifecycle->accept($id, $this->createUser());
    }

    public function testCannotAcceptOwnRequest(): void
    {
        $requester = $this->createUser();
        $id        = $this->createRequest($requester);

        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('own request');
        $this->lifecycle->accept($id, $requester);
    }

    public function testCodAcceptanceRequiresBalanceForPlatformFee(): void
    {
        $runner = $this->createUser();
        $id     = $this->createRequest($this->createUser(), ['payment_method' => 'cod', 'service_fee' => '50']);

        try {
            $this->lifecycle->accept($id, $runner);
            $this->fail('Runner without funds accepted a COD request.');
        } catch (ServiceException $e) {
            $this->assertStringContainsString('₱5.00', $e->getMessage());
        }

        $this->assertSame('pending', $this->requestRow($id)['status']);
        $this->assertSame(0, $this->countRows('accepted_requests'));
    }

    public function testCannotSkipOnTheWay(): void
    {
        $runner = $this->createUser();
        $id     = $this->createRequest($this->createUser());
        $this->lifecycle->accept($id, $runner);

        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('On the Way');
        $this->lifecycle->markDelivered($id, $runner, false);
    }

    public function testOnlyTheAssignedRunnerCanUpdateStatus(): void
    {
        $runner = $this->createUser();
        $id     = $this->createRequest($this->createUser());
        $this->lifecycle->accept($id, $runner);

        $this->expectException(ServiceException::class);
        $this->lifecycle->markOnTheWay($id, $this->createUser());
    }

    public function testWalletRequestCannotBeDeliveredBeforePayment(): void
    {
        $runner = $this->createUser();
        $id     = $this->createRequest($this->createUser());
        $this->lifecycle->accept($id, $runner);
        $this->lifecycle->markOnTheWay($id, $runner);

        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('must pay');
        $this->lifecycle->markDelivered($id, $runner, false);
    }

    public function testCodDeliveryRequiresCashConfirmation(): void
    {
        $runner = $this->createUser();
        $this->fund($runner, '10');
        $id = $this->createRequest($this->createUser(), ['payment_method' => 'cod']);
        $this->lifecycle->accept($id, $runner);
        $this->lifecycle->markOnTheWay($id, $runner);

        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('Confirm');
        $this->lifecycle->markDelivered($id, $runner, false);
    }

    public function testCodDeliveryDebitsPlatformFeeFromRunnerExactlyOnce(): void
    {
        $requester = $this->createUser();
        $runner    = $this->createUser();
        $this->fund($runner, '10');
        $id = $this->createRequest($requester, ['payment_method' => 'cod', 'estimated_price' => '180', 'service_fee' => '50']);

        $this->lifecycle->accept($id, $runner);
        $this->lifecycle->markOnTheWay($id, $runner);
        $this->lifecycle->markDelivered($id, $runner, true);

        $row = $this->requestRow($id);
        $this->assertSame('delivered', $row['status']);
        $this->assertSame('paid_cod', $row['payment_status']);
        $this->assertSame('5.00', $this->balance($runner));
        $this->assertSame('0.00', $this->balance($requester), 'Requester pays cash; their wallet is untouched.');
        $this->assertSame(1, $this->countRows('payments', ['request_id' => $id, 'type' => 'platform_fee']));
        $this->assertSame($this->balance($runner), $this->ledgerTotal($runner));

        try {
            $this->lifecycle->markDelivered($id, $runner, true);
            $this->fail('Delivered twice.');
        } catch (ServiceException $e) {
            $this->assertStringContainsString('already', $e->getMessage());
        }

        $this->assertSame('5.00', $this->balance($runner));
    }

    public function testCodDeliveryWithInsufficientRunnerBalanceChangesNothing(): void
    {
        $runner = $this->createUser();
        $this->fund($runner, '5');
        $id = $this->createRequest($this->createUser(), ['payment_method' => 'cod', 'service_fee' => '50']);
        $this->lifecycle->accept($id, $runner);
        $this->lifecycle->markOnTheWay($id, $runner);

        // Runner spends the credits elsewhere before delivering.
        $this->db->table('users')->where('id', $runner)->update(['wallet_balance' => '1.00']);

        try {
            $this->lifecycle->markDelivered($id, $runner, true);
            $this->fail('Delivered without fee funds.');
        } catch (ServiceException $e) {
            $this->assertStringContainsString('platform fee', $e->getMessage());
        }

        $row = $this->requestRow($id);
        $this->assertSame('on_the_way', $row['status']);
        $this->assertSame('cod_pending', $row['payment_status']);
        $this->assertSame(0, $this->countRows('payments', ['request_id' => $id]));
        $this->assertSame(0, $this->countRows('request_notifications', ['request_id' => $id, 'type' => 'delivered']));
    }

    public function testRequesterCanCancelOnlyWhilePending(): void
    {
        $requester = $this->createUser();
        $pending   = $this->createRequest($requester);
        $this->lifecycle->cancel($pending, $requester);
        $this->assertSame('cancelled', $this->requestRow($pending)['status']);
        $this->assertNotNull($this->requestRow($pending)['cancelled_at']);

        $accepted = $this->createRequest($requester);
        $this->lifecycle->accept($accepted, $this->createUser());

        $this->expectException(ServiceException::class);
        $this->lifecycle->cancel($accepted, $requester);
    }

    public function testOthersCannotCancelARequest(): void
    {
        $id = $this->createRequest($this->createUser());

        $this->expectException(ServiceException::class);
        $this->lifecycle->cancel($id, $this->createUser());
    }

    public function testAdminCanCancelUnpaidAcceptedRequestButNeverPaidOnes(): void
    {
        $admin     = $this->createUser(['role' => 'admin']);
        $requester = $this->createUser();
        $runner    = $this->createUser();

        $unpaid = $this->createRequest($requester);
        $this->lifecycle->accept($unpaid, $runner);
        $this->lifecycle->cancel($unpaid, $admin, true);

        $this->assertSame('cancelled', $this->requestRow($unpaid)['status']);
        $this->assertSame(1, $this->countRows('accepted_requests', ['request_id' => $unpaid, 'status' => 'cancelled']));
        $this->assertSame(1, $this->countRows('request_notifications', ['user_id' => $runner, 'type' => 'request_cancelled']));
        $this->assertSame(1, $this->countRows('request_notifications', ['user_id' => $requester, 'type' => 'request_cancelled']));

        $this->fund($requester, '500');
        $paid = $this->createRequest($requester);
        $this->lifecycle->accept($paid, $runner);
        $this->wallet->payForRequest($paid, $requester);

        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('Paid');
        $this->lifecycle->cancel($paid, $admin, true);
    }

    public function testCancelledRequestCannotBeProgressed(): void
    {
        $admin  = $this->createUser(['role' => 'admin']);
        $runner = $this->createUser();
        $id     = $this->createRequest($this->createUser());
        $this->lifecycle->accept($id, $runner);
        $this->lifecycle->cancel($id, $admin, true);

        $this->expectException(ServiceException::class);
        $this->lifecycle->markOnTheWay($id, $runner);
    }
}

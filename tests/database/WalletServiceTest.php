<?php

use App\Services\RequestLifecycle;
use App\Services\ServiceException;
use App\Services\WalletService;
use Tests\Support\DatabaseTestCase;

/**
 * @internal
 */
final class WalletServiceTest extends DatabaseTestCase
{
    private WalletService $wallet;
    private RequestLifecycle $lifecycle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->wallet    = new WalletService();
        $this->lifecycle = new RequestLifecycle();
    }

    public function testDemoTopUpIsLedgeredAndLabelledAsDemo(): void
    {
        $user = $this->createUser();

        $result = $this->wallet->addDemoCredits($user, '250.50', str_repeat('a', 32));

        $this->assertTrue($result['credited']);
        $this->assertSame('250.50', $this->balance($user));

        $entry = $this->db->table('payments')->where('user_id', $user)->get()->getRowArray();
        $this->assertSame('demo_top_up', $entry['type']);
        $this->assertSame('credit', $entry['direction']);
        $this->assertSame('demo', $entry['source']);
        $this->assertSame('250.50', $entry['balance_after']);
        $this->assertNull($entry['reference_no'], 'User-entered references are never stored as proof of funds.');
        $this->assertStringContainsString('not real money', $entry['description']);
    }

    public function testResubmittingTheSameTopUpFormCreditsOnce(): void
    {
        $user = $this->createUser();
        $key  = str_repeat('b', 32);

        $this->assertTrue($this->wallet->addDemoCredits($user, '100', $key)['credited']);
        $this->assertFalse($this->wallet->addDemoCredits($user, '100', $key)['credited']);

        $this->assertSame('100.00', $this->balance($user));
        $this->assertSame(1, $this->countRows('payments', ['user_id' => $user]));
    }

    public function testTopUpKeyCannotBeReplayedByAnotherUser(): void
    {
        $key = str_repeat('c', 32);
        $this->wallet->addDemoCredits($this->createUser(), '100', $key);

        $this->expectException(ServiceException::class);
        $this->wallet->addDemoCredits($this->createUser(), '100', $key);
    }

    public function testTopUpLimits(): void
    {
        $user = $this->createUser();

        foreach (['0', '-1', '5000.01', 'abc', '1.001'] as $bad) {
            try {
                $this->wallet->addDemoCredits($user, $bad, bin2hex(random_bytes(16)));
                $this->fail("Accepted top-up amount {$bad}");
            } catch (ServiceException) {
                $this->addToAssertionCount(1);
            }
        }

        foreach (range(1, 4) as $i) {
            $this->wallet->addDemoCredits($user, '5000', bin2hex(random_bytes(16)));
        }

        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('capped');
        $this->wallet->addDemoCredits($user, '0.01', bin2hex(random_bytes(16)));
    }

    public function testRejectsMalformedIdempotencyKey(): void
    {
        $this->expectException(ServiceException::class);
        $this->wallet->addDemoCredits($this->createUser(), '10', 'not-a-key');
    }

    public function testTopUpsAreDisabledOutsideDemoMode(): void
    {
        config('PasaBuy')->demoMode = false;

        try {
            $this->expectException(ServiceException::class);
            $this->wallet->addDemoCredits($this->createUser(), '10', str_repeat('d', 32));
        } finally {
            config('PasaBuy')->demoMode = true;
        }
    }

    public function testPayingRunnerMovesExactAmountsAndRecordsPlatformFee(): void
    {
        $requester = $this->createUser();
        $runner    = $this->createUser();
        $this->fund($requester, '1000');
        $id = $this->createRequest($requester, ['estimated_price' => '95.50', 'service_fee' => '45.00']);
        $this->lifecycle->accept($id, $runner);

        $this->wallet->payForRequest($id, $requester);

        $this->assertSame('859.50', $this->balance($requester));
        $this->assertSame('136.00', $this->balance($runner));
        $this->assertSame('paid', $this->requestRow($id)['payment_status']);

        $entries = $this->db->table('payments')->where('request_id', $id)->orderBy('id')->get()->getResultArray();
        $this->assertSame(
            [['request_payment', 'debit', '140.50'], ['request_earning', 'credit', '140.50'], ['platform_fee', 'debit', '4.50']],
            array_map(static fn ($e) => [$e['type'], $e['direction'], $e['amount']], $entries)
        );

        // Cached balances always equal the ledger.
        $this->assertSame($this->balance($requester), $this->ledgerTotal($requester));
        $this->assertSame($this->balance($runner), $this->ledgerTotal($runner));
    }

    public function testCannotPayTwice(): void
    {
        $requester = $this->createUser();
        $this->fund($requester, '1000');
        $id = $this->createRequest($requester);
        $this->lifecycle->accept($id, $this->createUser());
        $this->wallet->payForRequest($id, $requester);

        try {
            $this->wallet->payForRequest($id, $requester);
            $this->fail('Paid twice.');
        } catch (ServiceException $e) {
            $this->assertStringContainsString('already paid', $e->getMessage());
        }

        $this->assertSame('850.00', $this->balance($requester));
    }

    public function testInsufficientFundsChangeNothing(): void
    {
        $requester = $this->createUser();
        $runner    = $this->createUser();
        $this->fund($requester, '149.99');
        $id = $this->createRequest($requester); // total 150.00
        $this->lifecycle->accept($id, $runner);

        try {
            $this->wallet->payForRequest($id, $requester);
            $this->fail('Paid without enough funds.');
        } catch (ServiceException $e) {
            $this->assertStringContainsString('Insufficient', $e->getMessage());
        }

        $this->assertSame('149.99', $this->balance($requester));
        $this->assertSame('0.00', $this->balance($runner));
        $this->assertSame('unpaid', $this->requestRow($id)['payment_status']);
    }

    public function testOnlyTheRequesterCanPay(): void
    {
        $requester = $this->createUser();
        $other     = $this->createUser();
        $this->fund($other, '1000');
        $id = $this->createRequest($requester);
        $this->lifecycle->accept($id, $this->createUser());

        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('Only the requester');
        $this->wallet->payForRequest($id, $other);
    }

    public function testCannotPayBeforeAcceptanceOrForCod(): void
    {
        $requester = $this->createUser();
        $this->fund($requester, '1000');

        try {
            $this->wallet->payForRequest($this->createRequest($requester), $requester);
            $this->fail('Paid a pending request.');
        } catch (ServiceException) {
            $this->addToAssertionCount(1);
        }

        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('Cash-on-delivery');
        $this->wallet->payForRequest($this->createRequest($requester, ['payment_method' => 'cod']), $requester);
    }

    /**
     * Failure in the middle of settlement: a conflicting ledger row makes the
     * second insert violate UNIQUE(request_id, type). Everything written
     * before the failure (requester debit, balance update) must roll back.
     */
    public function testMidSettlementFailureRollsBackEverything(): void
    {
        $requester = $this->createUser();
        $runner    = $this->createUser();
        $this->fund($requester, '1000');
        $id = $this->createRequest($requester);
        $this->lifecycle->accept($id, $runner);

        $this->db->table('payments')->insert([
            'user_id' => $runner, 'request_id' => $id, 'amount' => '1.00', 'type' => 'request_earning',
            'direction' => 'credit', 'source' => 'test', 'status' => 'completed',
        ]);
        $paymentsBefore = $this->countRows('payments');

        try {
            $this->wallet->payForRequest($id, $requester);
            $this->fail('Settlement should have failed.');
        } catch (ServiceException $e) {
            $this->assertStringContainsString('already completed', $e->getMessage());
        }

        $this->assertSame('1000.00', $this->balance($requester), 'Requester debit was rolled back.');
        $this->assertSame('0.00', $this->balance($runner));
        $this->assertSame($paymentsBefore, $this->countRows('payments'));
        $this->assertSame('unpaid', $this->requestRow($id)['payment_status']);
        $this->assertSame(0, $this->countRows('request_notifications', ['type' => 'runner_paid']));
    }

    /**
     * Regression: CodeIgniter's strict transaction mode used to make every
     * later transaction on the same connection fail after one failure.
     */
    public function testAFailedOperationDoesNotPoisonLaterOnesOnTheSameConnection(): void
    {
        $requester = $this->createUser();
        $id        = $this->createRequest($requester);
        (new RequestLifecycle())->accept($id, $this->createUser());

        try {
            $this->wallet->payForRequest($id, $requester); // no funds -> fails
        } catch (ServiceException) {
        }

        $this->db->table('payments')->insert([
            'user_id' => $requester, 'request_id' => $id, 'amount' => '1.00', 'type' => 'request_payment',
            'direction' => 'debit', 'source' => 'test', 'status' => 'completed',
        ]);
        $this->fund($requester, '500');

        try {
            $this->wallet->payForRequest($id, $requester); // DB-level conflict -> fails
        } catch (ServiceException) {
        }

        $result = $this->wallet->addDemoCredits($requester, '10', bin2hex(random_bytes(16)));

        $this->assertTrue($result['credited']);
        $this->assertSame('510.00', $this->balance($requester));
    }

    public function testDatabaseRejectsNegativeBalancesEvenIfCodeIsBypassed(): void
    {
        $user = $this->createUser();

        $this->expectException(\CodeIgniter\Database\Exceptions\DatabaseException::class);
        $this->db->table('users')->where('id', $user)->update(['wallet_balance' => '-0.01']);
    }

    public function testDatabaseRejectsUnknownStatusesUnderStrictMode(): void
    {
        $id = $this->createRequest($this->createUser());

        $this->expectException(\CodeIgniter\Database\Exceptions\DatabaseException::class);
        $this->db->table('requests')->where('id', $id)->update(['payment_status' => 'refunded_maybe']);
    }
}

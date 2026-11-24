<?php

use App\Libraries\UploadStorage;
use App\Services\PriceApproval;
use App\Services\RequestLifecycle;
use App\Services\ServiceException;
use App\Services\WalletService;
use Tests\Support\FeatureTestCase;

/**
 * R5: actual price + private receipt + requester approval before payment.
 *
 * @internal
 */
final class PriceApprovalTest extends FeatureTestCase
{
    private PriceApproval $prices;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prices = new PriceApproval();
    }

    private function receipt(string $path = 'test_receipt.pdf'): array
    {
        return ['path' => $path, 'name' => 'receipt.pdf', 'type' => 'application/pdf'];
    }

    /** @return array{0: int, 1: int, 2: int} requester, runner, request */
    private function acceptedErrand(array $overrides = []): array
    {
        $requester = $this->createUser();
        $runner    = $this->createUser();
        $id        = $this->createRequest($requester, $overrides + ['estimated_price' => '100.00', 'service_fee' => '50.00']);

        if (($overrides['payment_method'] ?? 'wallet') === 'cod') {
            $this->fund($runner, '100'); // covers the platform fee charged at delivery
        }

        (new RequestLifecycle())->accept($id, $runner);

        return [$requester, $runner, $id];
    }

    private function assertRefused(callable $action, string $messagePart): void
    {
        try {
            $action();
            $this->fail('Expected a refusal containing: ' . $messagePart);
        } catch (ServiceException $e) {
            $this->assertStringContainsString($messagePart, $e->getMessage());
        }
    }

    // ------------------------------------------------------------ service rules

    public function testOnlyTheAssignedRunnerSubmitsAValidAmountWithAReceipt(): void
    {
        [$requester, $runner, $id] = $this->acceptedErrand();

        $this->assertRefused(fn () => $this->prices->submit($id, $requester, '100', $this->receipt()), 'Only the runner');
        $this->assertRefused(fn () => $this->prices->submit($id, $this->createUser(), '100', $this->receipt()), 'Only the runner');
        $this->assertRefused(fn () => $this->prices->submit($id, $runner, '0', $this->receipt()), 'from ₱0.01');
        $this->assertRefused(fn () => $this->prices->submit($id, $runner, 'abc', $this->receipt()), 'actual item amount');
        $this->assertRefused(fn () => $this->prices->submit($id, $runner, '100', ['path' => '', 'name' => '', 'type' => '']), 'receipt');
        $this->assertRefused(fn () => $this->prices->submit($id, $runner, '100', $this->receipt(), str_repeat('x', 256)), 'note');

        $pending = $this->createRequest($requester);
        $this->assertRefused(fn () => $this->prices->submit($pending, $runner, '100', $this->receipt()), 'accept the request first');

        $this->assertSame(0, $this->countRows('price_submissions'));
        $this->assertSame('awaiting_receipt', $this->requestRow($id)['price_status']);
    }

    public function testApprovalSetsTheTotalAndPaymentChargesTheApprovedPrice(): void
    {
        [$requester, $runner, $id] = $this->acceptedErrand();
        $this->fund($requester, '1000');

        $submission = $this->prices->submit($id, $runner, '120.00', $this->receipt(), 'Bigger pack only');
        $this->assertSame('submitted', $this->requestRow($id)['price_status']);
        $this->assertSame(1, $this->countRows('request_notifications', ['user_id' => $requester, 'type' => 'price_submitted']));

        $this->prices->approve($id, $requester, $submission);
        $row = $this->requestRow($id);
        $this->assertSame('approved', $row['price_status']);
        $this->assertSame('120.00', $row['approved_item_price']);
        $this->assertSame('170.00', $row['total_amount']);
        $this->assertSame('5.00', $row['platform_fee'], 'The platform fee is 10% of the service fee, unchanged.');

        (new WalletService())->payForRequest($id, $requester);
        $this->assertSame('830.00', $this->balance($requester));
        $this->assertSame(['request_payment', 'debit', '170.00'], (function () use ($id) {
            $p = $this->db->table('payments')->where('request_id', $id)->where('type', 'request_payment')->get()->getRowArray();

            return [$p['type'], $p['direction'], $p['amount']];
        })());
    }

    public function testARevisionCancelsTheApprovalAndOnlyTheLatestCanBeDecided(): void
    {
        [$requester, $runner, $id] = $this->acceptedErrand();
        $first = $this->prices->submit($id, $runner, '120.00', $this->receipt());
        $this->prices->approve($id, $requester, $first);

        $second = $this->prices->submit($id, $runner, '135.00', $this->receipt());
        $row    = $this->requestRow($id);
        $this->assertSame('submitted', $row['price_status']);
        $this->assertNull($row['approved_item_price']);
        $this->assertSame('150.00', $row['total_amount'], 'Back to the estimate until a new price is approved.');
        $this->assertSame(1, $this->countRows('price_submissions', ['id' => $first, 'status' => 'superseded']));

        $this->assertRefused(fn () => (new WalletService())->payForRequest($id, $requester), 'approve the actual price');
        $this->assertRefused(fn () => $this->prices->approve($id, $requester, $first), 'changed the price');
        $this->assertRefused(fn () => $this->prices->approve($id, $runner, $second), 'Only the requester');

        $this->prices->approve($id, $requester, $second);
        $this->assertRefused(fn () => $this->prices->approve($id, $requester, $second), 'already approved');
        $this->assertSame('185.00', $this->requestRow($id)['total_amount']);
    }

    public function testRejectionNeedsAReasonAndTheRunnerCanSendACorrection(): void
    {
        [$requester, $runner, $id] = $this->acceptedErrand(['payment_method' => 'cod']);
        $submission = $this->prices->submit($id, $runner, '300.00', $this->receipt());

        $this->assertRefused(fn () => $this->prices->reject($id, $requester, $submission, ' '), 'why');
        $this->prices->reject($id, $requester, $submission, 'Receipt total says 130, not 300');

        $this->assertSame('rejected', $this->requestRow($id)['price_status']);
        $this->assertSame(1, $this->countRows('price_submissions', ['id' => $submission, 'status' => 'rejected', 'decision_reason' => 'Receipt total says 130, not 300']));
        $this->assertSame(1, $this->countRows('request_notifications', ['user_id' => $runner, 'type' => 'price_rejected']));

        // Cash can't be settled on a rejected price.
        (new RequestLifecycle())->markOnTheWay($id, $runner);
        $this->assertRefused(fn () => (new RequestLifecycle())->markDelivered($id, $runner, true), 'approve the actual price');

        $fixed = $this->prices->submit($id, $runner, '130.00', $this->receipt());
        $this->prices->approve($id, $requester, $fixed);
        (new RequestLifecycle())->markDelivered($id, $runner, true);

        $row = $this->requestRow($id);
        $this->assertSame('paid_cod', $row['payment_status']);
        $this->assertSame('180.00', $row['total_amount']);
        $this->assertSame(2, $this->countRows('price_submissions', ['request_id' => $id]), 'Every submission is kept (audit trail).');
    }

    public function testThePriceIsFrozenOncePaidAndLegacySettledRequestsAreNeverAsked(): void
    {
        [$requester, $runner, $id] = $this->acceptedErrand();
        $this->fund($requester, '1000');
        $this->approvePrice($id, '110.00');
        (new WalletService())->payForRequest($id, $requester);

        $this->assertRefused(fn () => $this->prices->submit($id, $runner, '200.00', $this->receipt()), 'already paid');
        $this->assertSame('160.00', $this->requestRow($id)['total_amount']);

        $legacy = $this->createRequest($requester);
        $this->db->table('requests')->where('id', $legacy)->update(['price_status' => 'not_required', 'status' => 'delivered', 'payment_status' => 'paid']);
        $this->assertRefused(fn () => $this->prices->submit($legacy, $runner, '1', $this->receipt()), 'settled before price approval');
    }

    // ------------------------------------------------------------ HTTP + privacy

    public function testRequesterApprovesAndRejectsThroughThePage(): void
    {
        [$requester, $runner, $id] = $this->acceptedErrand();
        $first = $this->prices->submit($id, $runner, '90.00', $this->receipt());

        $this->assertRedirectsTo($this->postAs($runner, 'request/' . $id . '/price/' . $first . '/approve'), 'request-details/' . $id);
        $this->assertSame('submitted', $this->requestRow($id)['price_status'], 'The runner cannot approve their own price.');

        $this->postAs($requester, 'request/' . $id . '/price/' . $first . '/reject', ['reason' => 'Wrong item']);
        $this->assertSame('rejected', $this->requestRow($id)['price_status']);

        $second = $this->prices->submit($id, $runner, '95.00', $this->receipt());
        $this->postAs($requester, 'request/' . $id . '/price/' . $second . '/approve');
        $this->assertSame('approved', $this->requestRow($id)['price_status']);
        $this->assertSame('Price approved. The total now uses the actual price.', $this->flash('success'));

        // Without a CSRF token nothing happens.
        $third = $this->prices->submit($id, $runner, '96.00', $this->receipt());
        $this->postAs($requester, 'request/' . $id . '/price/' . $third . '/approve', [], 'user', false);
        $this->assertSame('submitted', $this->requestRow($id)['price_status']);
    }

    public function testSubmittingWithoutAReceiptFileIsRefused(): void
    {
        [, $runner, $id] = $this->acceptedErrand();

        $this->postAs($runner, 'request/' . $id . '/price', ['item_price' => '120.00']);

        $this->assertSame('Attach a photo or PDF of the receipt.', $this->flash('error'));
        $this->assertSame(0, $this->countRows('price_submissions'));
    }

    public function testReceiptsAreVisibleOnlyToTheRequesterRunnerAndAdmins(): void
    {
        [$requester, $runner, $id] = $this->acceptedErrand();
        $dir  = UploadStorage::directory(UploadStorage::RECEIPTS);
        is_dir($dir) || mkdir($dir, 0775, true);
        $name = 'test_' . bin2hex(random_bytes(6)) . '.pdf';
        file_put_contents($dir . $name, "%PDF-1.4\n%%EOF\n");
        $submission = $this->prices->submit($id, $runner, '100.00', $this->receipt($name));
        $url        = 'request/' . $id . '/receipt/' . $submission;

        try {
            foreach ([[$requester, 'user'], [$runner, 'user'], [$this->createUser(['role' => 'admin']), 'admin']] as [$viewer, $role]) {
                $response = $this->getAs($viewer, $url, $role);
                $response->assertOK();
                $this->assertSame('application/pdf', $response->response()->getHeaderLine('Content-Type'));
                $this->assertStringContainsString('sandbox', $response->response()->getHeaderLine('Content-Security-Policy'));
            }

            $outsider = $this->createUser();
            $this->assertNotFound(fn () => $this->getAs($outsider, $url));
            $this->assertNotFound(fn () => $this->getAs($outsider, $url, 'admin')); // forged session role
            $this->assertNotFound(fn () => $this->getAs($requester, 'request/' . ($id + 1) . '/receipt/' . $submission)); // wrong request
            $this->assertRedirectsTo($this->getAs(null, $url), 'login');
        } finally {
            @unlink($dir . $name);
        }
    }
}

<?php

use App\Services\RequestLifecycle;
use App\Services\ServiceException;
use App\Services\WalletService;
use Tests\Support\DatabaseTestCase;

/**
 * Real concurrency against MySQL/MariaDB: several separate PHP processes,
 * each with its own database connection, are released at the same instant
 * and attempt the same state change. SQLite or single-connection tests
 * cannot show these races.
 *
 * @internal
 */
final class ConcurrencyTest extends DatabaseTestCase
{
    private const WORKERS = 6;

    /**
     * Runs one worker process per job, all starting at the same moment.
     *
     * @param list<list<string|int>> $jobs [action, ...args]
     *
     * @return list<array{ok: bool, error?: string, result?: mixed}>
     */
    private function race(array $jobs): array
    {
        $script  = SUPPORTPATH . 'bin' . DIRECTORY_SEPARATOR . 'concurrency_worker.php';
        $startAt = sprintf('%.6F', microtime(true) + 3.0); // time for every process to boot
        $procs   = [];

        foreach ($jobs as $job) {
            $command = array_merge([PHP_BINARY, $script, $startAt], array_map('strval', $job));
            $pipes   = [];
            $proc    = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, ROOTPATH);

            $this->assertIsResource($proc, 'Could not start worker process.');
            $procs[] = [$proc, $pipes];
        }

        $results = [];

        foreach ($procs as [$proc, $pipes]) {
            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($proc);

            $decoded = json_decode(trim((string) $stdout), true);
            $this->assertIsArray($decoded, "Worker produced no JSON.\nstdout: {$stdout}\nstderr: {$stderr}");
            $results[] = $decoded;
        }

        // Every failure must be a clean business-rule rejection, never a crash.
        foreach ($results as $result) {
            if (! $result['ok']) {
                $this->assertSame(ServiceException::class, $result['class'], 'Unexpected worker error: ' . $result['error']);
            }
        }

        return $results;
    }

    private function successes(array $results): int
    {
        return count(array_filter($results, static fn ($r) => $r['ok']));
    }

    public function testSimultaneousAcceptsProduceExactlyOneAssignment(): void
    {
        $requester = $this->createUser();
        $requestId = $this->createRequest($requester);
        $runners   = array_map(fn () => $this->createUser(), range(1, self::WORKERS));

        $results = $this->race(array_map(static fn ($runner) => ['accept', $requestId, $runner], $runners));

        $this->assertSame(1, $this->successes($results), 'Exactly one runner wins.');
        $this->assertSame(1, $this->countRows('accepted_requests', ['request_id' => $requestId]));
        $this->assertSame('accepted', $this->requestRow($requestId)['status']);
        $this->assertSame(1, $this->countRows('request_notifications', ['request_id' => $requestId, 'type' => 'request_accepted']));
    }

    public function testSimultaneousPaymentsSettleOnce(): void
    {
        $requester = $this->createUser();
        $runner    = $this->createUser();
        $this->fund($requester, '5000');
        $requestId = $this->createRequest($requester, ['estimated_price' => '100', 'service_fee' => '50']);
        (new RequestLifecycle())->accept($requestId, $runner);

        $results = $this->race(array_fill(0, self::WORKERS, ['pay', $requestId, $requester]));

        $this->assertSame(1, $this->successes($results));
        $this->assertSame('4850.00', $this->balance($requester), 'Requester charged exactly once.');
        $this->assertSame('145.00', $this->balance($runner), 'Runner credited once, minus one platform fee.');
        $this->assertSame(3, $this->countRows('payments', ['request_id' => $requestId]));
        $this->assertSame($this->balance($requester), $this->ledgerTotal($requester));
        $this->assertSame($this->balance($runner), $this->ledgerTotal($runner));
    }

    public function testSimultaneousCodConfirmationsChargeThePlatformFeeOnce(): void
    {
        $runner = $this->createUser();
        $this->fund($runner, '100');
        $requestId = $this->createRequest($this->createUser(), ['payment_method' => 'cod', 'service_fee' => '50']);
        $lifecycle = new RequestLifecycle();
        $lifecycle->accept($requestId, $runner);
        $lifecycle->markOnTheWay($requestId, $runner);

        $results = $this->race(array_fill(0, self::WORKERS, ['deliver_cod', $requestId, $runner]));

        $this->assertSame(1, $this->successes($results));
        $this->assertSame('95.00', $this->balance($runner));
        $this->assertSame(1, $this->countRows('payments', ['request_id' => $requestId, 'type' => 'platform_fee']));
        $this->assertSame(1, $this->countRows('request_notifications', ['request_id' => $requestId, 'type' => 'delivered']));
        $this->assertSame('paid_cod', $this->requestRow($requestId)['payment_status']);
    }

    public function testDuplicateTopUpSubmissionsCreditOnce(): void
    {
        $user = $this->createUser();
        $key  = bin2hex(random_bytes(16));

        $results = $this->race(array_fill(0, self::WORKERS, ['topup', $user, '250.00', $key]));

        $credited = count(array_filter($results, static fn ($r) => $r['ok'] && $r['result']['credited'] === true));

        $this->assertSame(self::WORKERS, $this->successes($results), 'Repeats are harmless no-ops, not errors.');
        $this->assertSame(1, $credited);
        $this->assertSame('250.00', $this->balance($user));
        $this->assertSame(1, $this->countRows('payments', ['user_id' => $user]));
    }

    public function testConcurrentDistinctTopUpsLoseNoUpdates(): void
    {
        $user = $this->createUser();

        $jobs = array_map(static fn () => ['topup', $user, '100.00', bin2hex(random_bytes(16))], range(1, self::WORKERS));
        $results = $this->race($jobs);

        $this->assertSame(self::WORKERS, $this->successes($results));
        $this->assertSame(number_format(100 * self::WORKERS, 2, '.', ''), $this->balance($user));
        $this->assertSame($this->balance($user), $this->ledgerTotal($user));
    }

    public function testConcurrentPaymentsCannotOverdrawTheWallet(): void
    {
        $requester = $this->createUser();
        $this->fund($requester, '300'); // enough for two ₱150 requests, not three
        $lifecycle = new RequestLifecycle();
        $jobs      = [];

        foreach (range(1, 3) as $i) {
            $requestId = $this->createRequest($requester);
            $lifecycle->accept($requestId, $this->createUser());
            $jobs[] = ['pay', $requestId, $requester];
        }

        $results = $this->race($jobs);

        $this->assertSame(2, $this->successes($results));
        $this->assertSame('0.00', $this->balance($requester));
        $this->assertSame($this->balance($requester), $this->ledgerTotal($requester));
    }
}

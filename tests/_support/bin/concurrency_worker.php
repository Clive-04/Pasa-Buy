<?php

/*
 * Test-only worker for tests/database/ConcurrencyTest.php.
 *
 * Boots the application in the `testing` environment (so it can only use
 * the `tests` database connection), waits until a shared start time so all
 * workers hit MySQL at the same moment, performs one service call on its
 * own database connection, and prints a JSON result.
 *
 *   php tests/_support/bin/concurrency_worker.php <startAt> <action> <arg...>
 */

chdir(dirname(__DIR__, 3));

define('HOMEPATH', getcwd() . DIRECTORY_SEPARATOR);
define('CONFIGPATH', HOMEPATH . 'app' . DIRECTORY_SEPARATOR . 'Config' . DIRECTORY_SEPARATOR);
define('PUBLICPATH', HOMEPATH . 'public' . DIRECTORY_SEPARATOR);

require HOMEPATH . 'vendor/codeigniter4/framework/system/Test/bootstrap.php';

[$script, $startAt, $action] = array_slice($argv, 0, 3) + [null, null, null];
$args = array_slice($argv, 3);

try {
    $db = db_connect(); // connect before the barrier so only the work is timed

    $wait = (float) $startAt - microtime(true);

    if ($wait > 0) {
        usleep((int) ($wait * 1_000_000));
    }

    $result = match ($action) {
        'accept'      => (new App\Services\RequestLifecycle($db))->accept((int) $args[0], (int) $args[1]),
        'pay'         => (new App\Services\WalletService($db))->payForRequest((int) $args[0], (int) $args[1]),
        'deliver_cod' => (new App\Services\RequestLifecycle($db))->markDelivered((int) $args[0], (int) $args[1], true),
        'topup'       => (new App\Services\WalletService($db))->addDemoCredits((int) $args[0], (string) $args[1], (string) $args[2]),
        // R5: runner revises the price (placeholder receipt name; no file needed for the service).
        'revise_price' => (new App\Services\PriceApproval($db))->submit((int) $args[0], (int) $args[1], (string) $args[2],
            ['path' => 'race_receipt.pdf', 'name' => 'receipt.pdf', 'type' => 'application/pdf']),
        'approve_price' => (new App\Services\PriceApproval($db))->approve((int) $args[0], (int) $args[1], (int) $args[2]),
        default       => throw new InvalidArgumentException('Unknown action ' . $action),
    };

    echo json_encode(['ok' => true, 'result' => $result]);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'class' => $e::class, 'error' => $e->getMessage()]);
}

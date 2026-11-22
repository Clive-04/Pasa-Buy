<?php

namespace Tests\Support;

use App\Services\RequestLifecycle;
use App\Services\WalletService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;
use RuntimeException;

/**
 * Base class for tests that need the real MySQL/MariaDB schema.
 *
 * - The `tests` connection (Config\Database::$tests) is used automatically
 *   because ENVIRONMENT === 'testing'.
 * - The schema is rebuilt from the application migrations once per run,
 *   then every table is truncated before each test.
 * - Refuses to run unless the database name contains "test", so a
 *   misconfigured .env can never wipe a development or production database.
 */
abstract class DatabaseTestCase extends CIUnitTestCase
{
    protected const TABLES = [
        'wallet_topups', 'request_notifications', 'notifications', 'messages', 'payments', 'accepted_requests',
        'user_ratings', 'website_ratings', 'referrals', 'requests', 'users',
    ];

    public const PASSWORD = 'Password#123';

    private static bool $schemaReady = false;

    /** @var BaseConnection */
    protected $db;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = db_connect();

        // Never let real API keys from .env reach tests (no network, no
        // behaviour change). Integration tests set fake keys themselves.
        config('TalkJS')->appId = '';
        config('TalkJS')->secretKey = '';
        config('PayMongo')->secretKey = '';
        config('PayMongo')->webhookSecret = '';

        if (! str_contains((string) $this->db->getDatabase(), 'test')) {
            throw new RuntimeException('Refusing to run destructive tests against database "' . $this->db->getDatabase() . '".');
        }

        if (! self::$schemaReady) {
            $this->rebuildSchema();
            self::$schemaReady = true;
        }

        $this->db->query('SET FOREIGN_KEY_CHECKS = 0');

        foreach (self::TABLES as $table) {
            $this->db->query("TRUNCATE TABLE `{$table}`");
        }

        $this->db->query('SET FOREIGN_KEY_CHECKS = 1');

        // Login/registration throttling uses the cache; start each test clean.
        cache()->clean();
    }

    private function rebuildSchema(): void
    {
        $this->db->query('SET FOREIGN_KEY_CHECKS = 0');

        foreach ($this->db->query('SHOW TABLES')->getResultArray() as $row) {
            $this->db->query('DROP TABLE IF EXISTS `' . reset($row) . '`');
        }

        $this->db->query('SET FOREIGN_KEY_CHECKS = 1');

        $runner = \Config\Services::migrations(null, $this->db, false);
        $runner->setNamespace('App');

        if (! $runner->latest('tests')) {
            throw new RuntimeException('Test migrations failed.');
        }
    }

    // ------------------------------------------------------------ factories

    /** @param array<string, mixed> $overrides */
    protected function createUser(array $overrides = []): int
    {
        static $sequence = 0;
        $sequence++;

        $this->db->table('users')->insert($overrides + [
            'first_name'   => 'Test' . $sequence,
            'last_name'    => 'User',
            'email'        => 'user' . $sequence . '.' . uniqid() . '@example.test',
            'phone_number' => '0917' . str_pad((string) random_int(0, 9_999_999), 7, '0', STR_PAD_LEFT),
            'password'     => password_hash(self::PASSWORD, PASSWORD_DEFAULT),
            'address'      => 'Test Street',
        ]);

        return (int) $this->db->insertID();
    }

    protected function fund(int $userId, string $amount): void
    {
        (new WalletService())->addDemoCredits($userId, $amount, bin2hex(random_bytes(16)));
    }

    /** @param array<string, mixed> $overrides */
    protected function createRequest(int $requesterId, array $overrides = []): int
    {
        return (new RequestLifecycle())->create($requesterId, $overrides + [
            'item_name'        => 'Test item',
            'category'         => 'household',
            'quantity'         => '1',
            'brand'            => '',
            'item_notes'       => '',
            'store_name'       => 'Test Store',
            'alt_store'        => '',
            'store_address'    => 'Store Street',
            'delivery_address' => 'Home Street',
            'contact_number'   => '09170000000',
            'landmark'         => '',
            'delivery_notes'   => '',
            'estimated_price'  => '100.00',
            'service_fee'      => '50.00',
            'payment_method'   => 'wallet',
            'urgency'          => 'normal',
        ]);
    }

    protected function balance(int $userId): string
    {
        return (string) $this->db->table('users')->select('wallet_balance')->where('id', $userId)->get()->getRow()->wallet_balance;
    }

    /** Sum of the user's ledger (credits − debits) as a DECIMAL string. */
    protected function ledgerTotal(int $userId): string
    {
        $row = $this->db->query(
            "SELECT COALESCE(SUM(CASE WHEN direction = 'credit' THEN amount ELSE -amount END), 0) AS total FROM payments WHERE user_id = ?",
            [$userId]
        )->getRow();

        return number_format((float) $row->total, 2, '.', '');
    }

    protected function requestRow(int $requestId): array
    {
        return $this->db->table('requests')->where('id', $requestId)->get()->getRowArray();
    }

    protected function countRows(string $table, array $where = []): int
    {
        return $this->db->table($table)->where($where)->countAllResults();
    }
}

<?php

namespace App\Database\Seeds;

use App\Services\RequestLifecycle;
use App\Services\WalletService;
use CodeIgniter\Database\Seeder;
use RuntimeException;

/**
 * Synthetic demo data — no real people, addresses or phone numbers.
 * Emails use the reserved .test domain.
 *
 * Every balance and payment is created through the real services, so the
 * seeded ledger reconciles exactly like live usage would.
 *
 *   php spark db:seed DemoSeeder
 *
 * Password: DEMO_SEED_PASSWORD from the environment. Outside production a
 * documented local default is used; in production the variable is required.
 */
class DemoSeeder extends Seeder
{
    public const DOMAIN = 'demo.pasabuy.test';

    public function run(): void
    {
        $db = $this->db;

        if ($db->table('users')->where('email', 'admin@' . self::DOMAIN)->countAllResults() > 0) {
            echo "Demo data already present; nothing to do.\n";

            return;
        }

        $password = getenv('DEMO_SEED_PASSWORD') ?: null;

        if ($password === null) {
            if (ENVIRONMENT === 'production') {
                throw new RuntimeException('Set DEMO_SEED_PASSWORD before seeding a production-mode database.');
            }

            $password = 'DemoPass#2026';
        }

        if (strlen($password) < 8) {
            throw new RuntimeException('DEMO_SEED_PASSWORD must be at least 8 characters.');
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);

        $people = [
            'admin'      => ['Ada', 'Admin', 'admin', '09170000001'],
            'requester'  => ['Maria', 'Requester', 'user', '09170000002'],
            'runner'     => ['Jose', 'Runner', 'user', '09170000003'],
            'runner2'    => ['Ana', 'Courier', 'user', '09170000004'],
            'requester2' => ['Ben', 'Neighbor', 'user', '09170000005'],
        ];

        $ids = [];

        foreach ($people as $key => [$first, $last, $role, $phone]) {
            $db->table('users')->insert([
                'first_name'   => $first,
                'last_name'    => $last,
                'email'        => $key . '@' . self::DOMAIN,
                'phone_number' => $phone,
                'password'     => $hash,
                'address'      => 'Demo Street ' . (count($ids) + 1) . ', Sample Town',
                'username'     => strtolower($first . '.' . $last),
                'about_me'     => 'Synthetic demo account.',
                'role'         => $role,
            ]);
            $ids[$key] = (int) $db->insertID();
        }

        $wallet    = new WalletService($db);
        $lifecycle = new RequestLifecycle($db);
        $key       = static fn (): string => bin2hex(random_bytes(16));

        $wallet->addDemoCredits($ids['requester'], '3000.00', $key());
        $wallet->addDemoCredits($ids['requester2'], '800.00', $key());
        $wallet->addDemoCredits($ids['runner'], '500.00', $key());
        $wallet->addDemoCredits($ids['runner2'], '200.00', $key());

        $base = [
            'brand' => '', 'item_notes' => '', 'alt_store' => '', 'landmark' => 'Near the chapel',
            'delivery_notes' => '', 'quantity' => '1', 'urgency' => 'normal', 'category' => 'household',
            'store_address' => 'Demo Market, Sample Town', 'delivery_address' => 'Demo Street 2, Sample Town',
            'contact_number' => '09170000002',
        ];

        // 1. Open wallet request, waiting for a runner.
        $lifecycle->create($ids['requester'], $base + [
            'item_name' => 'Rice (5 kg)', 'store_name' => 'Demo Grocery',
            'estimated_price' => '320.00', 'service_fee' => '60.00', 'payment_method' => 'wallet',
        ]);

        // 2. Wallet request, fully completed.
        $done = $lifecycle->create($ids['requester'], [
            'item_name' => 'Paracetamol 500 mg (20 tabs)', 'store_name' => 'Demo Pharmacy', 'category' => 'medicine',
            'estimated_price' => '95.50', 'service_fee' => '45.00', 'payment_method' => 'wallet', 'urgency' => 'urgent',
        ] + $base);
        $lifecycle->accept($done, $ids['runner']);
        $lifecycle->markOnTheWay($done, $ids['runner']);
        $wallet->payForRequest($done, $ids['requester']);
        $lifecycle->markDelivered($done, $ids['runner'], false);

        // 3. Cash-on-delivery request, completed (platform fee debited from runner).
        $cod = $lifecycle->create($ids['requester2'], [
            'item_name' => 'School notebooks (pack of 6)', 'store_name' => 'Demo Bookstore', 'category' => 'school_supplies',
            'estimated_price' => '180.00', 'service_fee' => '50.00', 'payment_method' => 'cod',
            'contact_number' => '09170000005', 'delivery_address' => 'Demo Street 5, Sample Town',
        ] + $base);
        $lifecycle->accept($cod, $ids['runner2']);
        $lifecycle->markOnTheWay($cod, $ids['runner2']);
        $lifecycle->markDelivered($cod, $ids['runner2'], true);

        // 4. Cash-on-delivery request, accepted and in progress.
        $active = $lifecycle->create($ids['requester'], [
            'item_name' => 'Laundry detergent', 'store_name' => 'Demo Grocery',
            'estimated_price' => '150.00', 'service_fee' => '40.00', 'payment_method' => 'cod', 'urgency' => 'asap',
        ] + $base);
        $lifecycle->accept($active, $ids['runner']);

        // 5. Cancelled before anyone accepted it.
        $cancelled = $lifecycle->create($ids['requester2'], [
            'item_name' => 'Bread loaf', 'store_name' => 'Demo Bakery', 'category' => 'food',
            'estimated_price' => '75.00', 'service_fee' => '25.00', 'payment_method' => 'wallet',
            'contact_number' => '09170000005',
        ] + $base);
        $lifecycle->cancel($cancelled, $ids['requester2']);

        // Conversation about the completed request.
        foreach ([
            [$ids['runner'], $ids['requester'], 'Hi! I am at the pharmacy now. They have the 20-tablet pack.'],
            [$ids['requester'], $ids['runner'], 'Great, thank you. I paid through the wallet.'],
            [$ids['runner'], $ids['requester'], 'Received. On my way!'],
        ] as [$from, $to, $text]) {
            $db->table('messages')->insert([
                'sender_id' => $from, 'receiver_id' => $to, 'request_id' => $done,
                'message' => $text, 'created_at' => date('Y-m-d H:i:s'),
            ]);
            $db->table('notifications')->insert([
                'user_id' => $to, 'sender_id' => $from, 'message_id' => $db->insertID(),
                'type' => 'message', 'is_read' => 0, 'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        $db->table('user_ratings')->insert([
            'rater_id' => $ids['requester'], 'rated_user_id' => $ids['runner'],
            'rating' => 5, 'feedback' => 'Fast and friendly (demo rating).',
        ]);

        $db->table('referrals')->insert([
            'user_id' => $ids['requester'], 'friend_name' => 'Demo Friend',
            'friend_email' => 'friend@' . self::DOMAIN, 'status' => 'pending',
        ]);

        echo 'Seeded 5 demo accounts (*@' . self::DOMAIN . ") and 5 requests.\n";
    }
}

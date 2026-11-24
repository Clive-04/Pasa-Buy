<?php

namespace App\Database\Seeds;

use App\Libraries\UploadStorage;
use App\Services\PriceApproval;
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
 * Password: DEMO_SEED_PASSWORD from .env or the environment, always required
 * (no built-in default, so no demo account ever has a publicly known password).
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

        // Always required: the demo accounts include an admin, and a default
        // written in this (public) repository would be a known password on
        // any server that was ever seeded without the variable.
        $password = getenv('DEMO_SEED_PASSWORD') ?: (env('DEMO_SEED_PASSWORD') ?: null);

        if ($password === null) {
            throw new RuntimeException('Set DEMO_SEED_PASSWORD (at least 8 characters) in .env or the environment before seeding demo accounts.');
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
        $prices    = new PriceApproval($db);
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
        $prices->submit($done, $ids['runner'], '92.75', $this->demoReceipt('Demo Pharmacy', '92.75'), 'Synthetic demo receipt');
        $prices->approve($done, $ids['requester'], $this->latestSubmission($done));
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
        $prices->submit($cod, $ids['runner2'], '180.00', $this->demoReceipt('Demo Bookstore', '180.00'));
        $prices->approve($cod, $ids['requester2'], $this->latestSubmission($cod));
        $lifecycle->markDelivered($cod, $ids['runner2'], true);

        // 4. Cash-on-delivery request, accepted and in progress.
        $active = $lifecycle->create($ids['requester'], [
            'item_name' => 'Laundry detergent', 'store_name' => 'Demo Grocery',
            'estimated_price' => '150.00', 'service_fee' => '40.00', 'payment_method' => 'cod', 'urgency' => 'asap',
        ] + $base);
        $lifecycle->accept($active, $ids['runner']);
        // The runner sent the actual price; it waits for the requester's approval (R5 demo).
        $prices->submit($active, $ids['runner'], '158.00', $this->demoReceipt('Demo Grocery', '158.00'), 'Only the 1 kg pack was left (synthetic demo)');

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
            'rater_id' => $ids['requester'], 'rated_user_id' => $ids['runner'], 'request_id' => $done,
            'rating' => 5, 'feedback' => 'Fast and friendly (demo rating).',
        ]);

        $db->table('referrals')->insert([
            'user_id' => $ids['requester'], 'friend_name' => 'Demo Friend',
            'friend_email' => 'friend@' . self::DOMAIN, 'status' => 'pending',
        ]);

        echo 'Seeded 5 demo accounts (*@' . self::DOMAIN . ") and 5 requests.\n";
    }

    /**
     * A tiny synthetic PDF "receipt" stored privately, so demo errands show the
     * R5 approval flow. Clearly labelled as demo data; no real purchase.
     *
     * @return array{path: string, name: string, type: string}
     */
    private function demoReceipt(string $store, string $amount): array
    {
        $text = "DEMO RECEIPT - NOT A REAL PURCHASE  {$store}  Total PHP {$amount}";
        $stream = 'BT /F1 11 Tf 40 760 Td (' . str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text) . ') Tj ET';
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            '<< /Length ' . strlen($stream) . " >>\nstream\n{$stream}\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $i => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1) . " 0 obj\n{$object}\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= 'xref' . "\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        $pdf .= 'trailer << /Size ' . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";

        $directory = UploadStorage::directory(UploadStorage::RECEIPTS);
        is_dir($directory) || mkdir($directory, 0775, true);
        $name = 'demo_' . bin2hex(random_bytes(10)) . '.pdf';
        file_put_contents($directory . $name, $pdf);

        return ['path' => $name, 'name' => 'demo-receipt.pdf', 'type' => 'application/pdf'];
    }

    private function latestSubmission(int $requestId): int
    {
        return (int) $this->db->table('price_submissions')->selectMax('id', 'id')->where('request_id', $requestId)->get()->getRow('id');
    }
}

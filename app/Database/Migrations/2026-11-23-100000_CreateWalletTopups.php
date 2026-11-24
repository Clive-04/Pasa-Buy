<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Wallet top-ups paid through PayMongo (GCash / Maya).
 * A row is created as `pending` before redirecting to checkout and becomes
 * `paid` only after PayMongo confirms payment server-to-server (webhook or
 * a server-side retrieve of the checkout session). The ledger credit uses
 * idempotency key "pm_<checkout_session_id>", so it can happen only once.
 */
class CreateWalletTopups extends Migration
{
    public function up(): void
    {
        $this->db->query("CREATE TABLE `wallet_topups` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) NOT NULL,
            `amount` decimal(10,2) NOT NULL,
            `provider` varchar(20) NOT NULL DEFAULT 'paymongo',
            `checkout_session_id` varchar(64) DEFAULT NULL,
            `status` enum('pending','paid','failed') NOT NULL DEFAULT 'pending',
            `payment_id` varchar(64) DEFAULT NULL,
            `payment_method` varchar(30) DEFAULT NULL,
            `livemode` tinyint(1) NOT NULL DEFAULT 0,
            `created_at` datetime NOT NULL DEFAULT current_timestamp(),
            `paid_at` datetime DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_topups_session` (`checkout_session_id`),
            KEY `idx_topups_user` (`user_id`, `created_at`),
            CONSTRAINT `chk_topups_amount_positive` CHECK (`amount` > 0),
            CONSTRAINT `topups_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    }

    public function down(): void
    {
        $this->db->query('DROP TABLE IF EXISTS `wallet_topups`');
    }
}

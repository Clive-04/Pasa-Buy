<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Release hardening for the first (demo) release.
 *
 * Business rules approved on 2026-11-22:
 *  - Platform fee = 10% of the service fee, borne by the runner.
 *  - Wallet funds are simulated demo credits, never real money.
 *
 * Structural changes:
 *  - users.role is an explicit NOT NULL enum; wallet balance cannot go negative.
 *  - requests.tax is replaced by requests.platform_fee (the old 10%-of-item
 *    "tax" was never a legal tax and is superseded by the approved rule);
 *    cancelled status, wallet payment method and a strict payment_status enum.
 *  - payments becomes a per-wallet ledger: every row belongs to one wallet
 *    (user_id), has a direction, an amount > 0, balance_after, and database
 *    uniqueness that makes duplicate settlement impossible.
 *  - Financial history is no longer cascade-deleted with users/requests.
 *  - Message attachment paths move to private storage (writable/uploads).
 *
 * Existing-data handling (rehearsed on a copy of the 2026-05-01 data):
 *  - Legacy payment rows (type stored as '' by non-strict mode) are
 *    classified from request_id/description.
 *  - Requests already settled before this migration get platform_fee = 0,
 *    because no platform fee was ever recorded in the ledger for them; the
 *    admin report therefore reconciles. Unsettled requests use the new rule.
 *
 * down() restores the previous structure but cannot restore the original
 * '' payment types or old tax values. Restore from backup if exact legacy
 * data is required.
 */
class HardenRequestsAndLedger extends Migration
{
    public function up(): void
    {
        $db = $this->db;

        // ---------------------------------------------------------- users
        $db->query("UPDATE `users` SET `role` = 'user' WHERE `role` IS NULL OR `role` NOT IN ('user', 'admin')");
        $db->query("ALTER TABLE `users`
            MODIFY `role` ENUM('user','admin') NOT NULL DEFAULT 'user',
            ADD CONSTRAINT `chk_users_wallet_nonnegative` CHECK (`wallet_balance` >= 0)");

        // ------------------------------------------------------- requests
        $db->query("UPDATE `requests` SET `quantity` = 1 WHERE `quantity` IS NULL OR `quantity` < 1");
        $db->query("UPDATE `requests` SET `estimated_price` = 0 WHERE `estimated_price` IS NULL");
        $db->query("UPDATE `requests` SET `service_fee` = 0 WHERE `service_fee` IS NULL");

        $db->query("ALTER TABLE `requests`
            CHANGE `tax` `platform_fee` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            MODIFY `quantity` INT(11) NOT NULL DEFAULT 1,
            MODIFY `estimated_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            MODIFY `service_fee` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            MODIFY `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            MODIFY `payment_method` ENUM('cod','wallet','gcash','maya','bank_transfer') NOT NULL,
            MODIFY `status` ENUM('pending','accepted','on_the_way','delivered','cancelled') NOT NULL DEFAULT 'pending',
            ADD `cancelled_at` DATETIME NULL DEFAULT NULL AFTER `paid_at`");

        // payment_status: normalise anything unexpected, then make it strict.
        $db->query("UPDATE `requests` SET `payment_status` = 'unpaid'
            WHERE `payment_status` NOT IN ('unpaid','cod_pending','paid','paid_cod')");
        $db->query("ALTER TABLE `requests`
            MODIFY `payment_status` ENUM('unpaid','cod_pending','paid','paid_cod') NOT NULL DEFAULT 'unpaid'");

        // Amount bookkeeping under the approved rule.
        $db->query("UPDATE `requests` SET `total_amount` = `estimated_price` + `service_fee`");
        $db->query("UPDATE `requests` SET `platform_fee` = 0.00 WHERE `payment_status` IN ('paid','paid_cod')");
        $db->query("UPDATE `requests` SET `platform_fee` = ROUND(`service_fee` * 0.10, 2)
            WHERE `payment_status` NOT IN ('paid','paid_cod')");

        $db->query("ALTER TABLE `requests`
            ADD CONSTRAINT `chk_requests_quantity` CHECK (`quantity` BETWEEN 1 AND 999),
            ADD CONSTRAINT `chk_requests_amounts` CHECK (`estimated_price` >= 0 AND `service_fee` >= 0 AND `platform_fee` >= 0 AND `platform_fee` <= `service_fee`),
            ADD KEY `idx_status_created` (`status`, `created_at`)");

        // Keep financial history: requests must not vanish with their owner.
        $db->query('ALTER TABLE `requests` DROP FOREIGN KEY `requests_ibfk_1`');
        $db->query('ALTER TABLE `requests` ADD CONSTRAINT `requests_ibfk_1`
            FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT');

        // ---------------------------------------------- accepted_requests
        $db->query("ALTER TABLE `accepted_requests`
            MODIFY `status` ENUM('accepted','on_the_way','delivered','cancelled') NOT NULL DEFAULT 'accepted'");

        // ------------------------------------------------------- payments
        $db->query("ALTER TABLE `payments`
            MODIFY `type` VARCHAR(30) NOT NULL,
            ADD `direction` ENUM('credit','debit') NULL DEFAULT NULL AFTER `type`,
            ADD `balance_after` DECIMAL(10,2) NULL DEFAULT NULL AFTER `amount`,
            ADD `idempotency_key` VARCHAR(64) NULL DEFAULT NULL AFTER `reference_no`");

        // Classify legacy rows. Non-strict mode stored '' for every type the
        // old code wrote (request_payment, commission_received, top_up).
        $db->query("UPDATE `payments` SET `direction` = `type`, `type` = CONCAT('legacy_', `type`)
            WHERE `type` IN ('credit', 'debit')");
        $db->query("UPDATE `payments` SET `type` = 'request_payment', `direction` = 'debit'
            WHERE `direction` IS NULL AND `request_id` IS NOT NULL AND `description` LIKE 'Paid runner%'");
        $db->query("UPDATE `payments` SET `type` = 'request_earning', `direction` = 'credit'
            WHERE `direction` IS NULL AND `request_id` IS NOT NULL AND `description` LIKE 'Received payment%'");
        $db->query("UPDATE `payments` SET `type` = 'legacy_top_up', `direction` = 'credit'
            WHERE `direction` IS NULL AND `request_id` IS NULL");
        $db->query("UPDATE `payments` SET `type` = 'legacy_unclassified', `direction` = 'credit'
            WHERE `direction` IS NULL");

        $db->query("ALTER TABLE `payments`
            MODIFY `direction` ENUM('credit','debit') NOT NULL,
            ADD CONSTRAINT `chk_payments_amount_positive` CHECK (`amount` > 0),
            ADD UNIQUE KEY `uq_payments_request_type` (`request_id`, `type`),
            ADD UNIQUE KEY `uq_payments_idempotency` (`idempotency_key`),
            ADD KEY `idx_payments_user_created` (`user_id`, `created_at`),
            ADD KEY `idx_payments_type` (`type`),
            ADD KEY `idx_payments_receiver` (`receiver_id`)");

        $db->query('ALTER TABLE `payments` DROP FOREIGN KEY `payments_ibfk_1`');
        $db->query('ALTER TABLE `payments`
            ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
            ADD CONSTRAINT `payments_request_fk` FOREIGN KEY (`request_id`) REFERENCES `requests` (`id`) ON DELETE RESTRICT,
            ADD CONSTRAINT `payments_receiver_fk` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE SET NULL');

        // ------------------------------------------------------- messages
        $db->query("UPDATE `messages`
            SET `attachment_path` = SUBSTRING(`attachment_path`, CHAR_LENGTH('uploads/') + 1)
            WHERE `attachment_path` LIKE 'uploads/message_attachments/%'");

        // -------------------------------------------- ratings / referrals
        $db->query('ALTER TABLE `website_ratings` ADD UNIQUE KEY `uq_website_rating_user` (`user_id`)');
        $db->query('ALTER TABLE `referrals` ADD UNIQUE KEY `uq_referral_user_email` (`user_id`, `friend_email`)');
        $db->query('ALTER TABLE `user_ratings` ADD CONSTRAINT `chk_user_ratings_range` CHECK (`rating` BETWEEN 1 AND 5)');
        $db->query('ALTER TABLE `website_ratings` ADD CONSTRAINT `chk_website_ratings_range` CHECK (`rating` BETWEEN 1 AND 5)');

        // -------------------------------------------------- notifications
        $db->query('ALTER TABLE `request_notifications` ADD KEY `idx_rn_user_read` (`user_id`, `is_read`)');
        $db->query('ALTER TABLE `notifications` ADD KEY `idx_n_user_type_read` (`user_id`, `type`, `is_read`)');
    }

    public function down(): void
    {
        $db = $this->db;

        $db->query('ALTER TABLE `notifications` DROP KEY `idx_n_user_type_read`');
        $db->query('ALTER TABLE `request_notifications` DROP KEY `idx_rn_user_read`');

        $db->query('ALTER TABLE `website_ratings` DROP CONSTRAINT `chk_website_ratings_range`');
        $db->query('ALTER TABLE `user_ratings` DROP CONSTRAINT `chk_user_ratings_range`');
        $db->query('ALTER TABLE `referrals` DROP KEY `uq_referral_user_email`');
        $db->query('ALTER TABLE `website_ratings` DROP KEY `uq_website_rating_user`');

        $db->query("UPDATE `messages` SET `attachment_path` = CONCAT('uploads/', `attachment_path`)
            WHERE `attachment_path` LIKE 'message_attachments/%'");

        $db->query('ALTER TABLE `payments`
            DROP FOREIGN KEY `payments_receiver_fk`,
            DROP FOREIGN KEY `payments_request_fk`,
            DROP FOREIGN KEY `payments_ibfk_1`');
        $db->query('ALTER TABLE `payments` ADD CONSTRAINT `payments_ibfk_1`
            FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE');
        $db->query('ALTER TABLE `payments`
            DROP CONSTRAINT `chk_payments_amount_positive`,
            DROP KEY `uq_payments_request_type`,
            DROP KEY `uq_payments_idempotency`,
            DROP KEY `idx_payments_user_created`,
            DROP KEY `idx_payments_type`,
            DROP KEY `idx_payments_receiver`');
        // Previous enum could not represent any real ledger type.
        $db->query("UPDATE `payments` SET `type` = `direction`");
        $db->query("ALTER TABLE `payments`
            DROP COLUMN `idempotency_key`,
            DROP COLUMN `balance_after`,
            DROP COLUMN `direction`,
            MODIFY `type` ENUM('credit','debit') NOT NULL");

        $db->query("UPDATE `accepted_requests` SET `status` = 'accepted' WHERE `status` = 'cancelled'");
        $db->query("ALTER TABLE `accepted_requests`
            MODIFY `status` ENUM('accepted','on_the_way','delivered') NOT NULL DEFAULT 'accepted'");

        $db->query('ALTER TABLE `requests` DROP FOREIGN KEY `requests_ibfk_1`');
        $db->query('ALTER TABLE `requests` ADD CONSTRAINT `requests_ibfk_1`
            FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE');
        $db->query('ALTER TABLE `requests`
            DROP CONSTRAINT `chk_requests_quantity`,
            DROP CONSTRAINT `chk_requests_amounts`,
            DROP KEY `idx_status_created`');
        $db->query("UPDATE `requests` SET `status` = 'pending' WHERE `status` = 'cancelled'");
        $db->query("UPDATE `requests` SET `payment_method` = 'gcash' WHERE `payment_method` = 'wallet'");
        $db->query("ALTER TABLE `requests`
            DROP COLUMN `cancelled_at`,
            CHANGE `platform_fee` `tax` DECIMAL(10,2) DEFAULT 0.00,
            MODIFY `quantity` INT(11) DEFAULT 1,
            MODIFY `estimated_price` DECIMAL(10,2) DEFAULT 0.00,
            MODIFY `service_fee` DECIMAL(10,2) DEFAULT 0.00,
            MODIFY `total_amount` DECIMAL(10,2) DEFAULT 0.00,
            MODIFY `payment_method` ENUM('cod','gcash','maya','bank_transfer') NOT NULL,
            MODIFY `payment_status` VARCHAR(30) NOT NULL DEFAULT 'unpaid',
            MODIFY `status` ENUM('pending','accepted','on_the_way','delivered') NOT NULL DEFAULT 'pending'");

        $db->query('ALTER TABLE `users` DROP CONSTRAINT `chk_users_wallet_nonnegative`');
        $db->query("ALTER TABLE `users` MODIFY `role` VARCHAR(20) DEFAULT 'user'");
    }
}

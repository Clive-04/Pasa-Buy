<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * R5: actual price + private receipt, approved by the requester before any
 * wallet payment or cash-on-delivery settlement.
 *
 *  - price_submissions keeps every submission and decision (audit trail);
 *    nothing is edited in place.
 *  - requests.price_status: awaiting_receipt → submitted → approved/rejected;
 *    a new submission replaces the pending or approved one and cancels the approval.
 *  - requests.approved_item_price is what gets paid (plus the unchanged
 *    service fee); it is frozen once the request is paid.
 *  - Existing settled, delivered or cancelled requests are marked
 *    'not_required': no approval is demanded retroactively and no receipt
 *    evidence is invented.
 *
 * down() drops the table and columns: receipts already stored on disk stay
 * in writable/uploads/receipts but are no longer referenced.
 */
class CreatePriceApprovals extends Migration
{
    public function up(): void
    {
        $this->db->query("CREATE TABLE `price_submissions` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `request_id` int(11) NOT NULL,
            `runner_id` int(11) NOT NULL,
            `item_price` decimal(10,2) NOT NULL,
            `receipt_path` varchar(255) NOT NULL,
            `receipt_name` varchar(255) NOT NULL,
            `receipt_type` varchar(100) NOT NULL,
            `note` varchar(255) NULL DEFAULT NULL,
            `status` enum('pending','approved','rejected','superseded') NOT NULL DEFAULT 'pending',
            `decision_reason` varchar(255) NULL DEFAULT NULL,
            `decided_by` int(11) NULL DEFAULT NULL,
            `decided_at` datetime NULL DEFAULT NULL,
            `created_at` datetime NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_price_submissions_request` (`request_id`, `id`),
            CONSTRAINT `price_sub_request_fk` FOREIGN KEY (`request_id`) REFERENCES `requests` (`id`) ON DELETE CASCADE,
            CONSTRAINT `price_sub_runner_fk` FOREIGN KEY (`runner_id`) REFERENCES `users` (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        $this->db->query("ALTER TABLE `requests`
            ADD COLUMN `price_status` enum('not_required','awaiting_receipt','submitted','approved','rejected') NOT NULL DEFAULT 'awaiting_receipt' AFTER `payment_status`,
            ADD COLUMN `approved_item_price` decimal(10,2) NULL DEFAULT NULL AFTER `price_status`,
            ADD COLUMN `approved_submission_id` int(11) NULL DEFAULT NULL AFTER `approved_item_price`");

        // Never demand approval for requests that are already settled or closed.
        $this->db->query("UPDATE `requests` SET `price_status` = 'not_required'
            WHERE `payment_status` IN ('paid', 'paid_cod') OR `status` IN ('delivered', 'cancelled')");
    }

    public function down(): void
    {
        $this->db->query('ALTER TABLE `requests`
            DROP COLUMN `approved_submission_id`,
            DROP COLUMN `approved_item_price`,
            DROP COLUMN `price_status`');
        $this->db->query('DROP TABLE IF EXISTS `price_submissions`');
    }
}

<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Abuse controls for the built-in chat (decision of 2026-11-23, R4):
 *
 *  - user_blocks: a block stops NEW messages in both directions. It never
 *    deletes messages, requests or payments, and it doesn't stop an errand.
 *  - user_reports: reports reviewed by admins in admin/reports. A report
 *    may point at the request and/or message it is about (evidence).
 *  - messages index on (receiver_id, sender_id, id) for the polling cursor.
 *
 * down() drops both tables (their rows are lost) and the index. Messages
 * and notifications are untouched in both directions.
 */
class CreateChatModeration extends Migration
{
    public function up(): void
    {
        $options = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci';

        $this->db->query("CREATE TABLE `user_blocks` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `blocker_id` int(11) NOT NULL,
            `blocked_id` int(11) NOT NULL,
            `created_at` datetime NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_block_pair` (`blocker_id`, `blocked_id`),
            KEY `idx_blocked` (`blocked_id`),
            CONSTRAINT `chk_block_not_self` CHECK (`blocker_id` <> `blocked_id`),
            CONSTRAINT `blocks_blocker_fk` FOREIGN KEY (`blocker_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
            CONSTRAINT `blocks_blocked_fk` FOREIGN KEY (`blocked_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) {$options}");

        $this->db->query("CREATE TABLE `user_reports` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `reporter_id` int(11) NOT NULL,
            `reported_user_id` int(11) NOT NULL,
            `request_id` int(11) DEFAULT NULL,
            `message_id` int(11) DEFAULT NULL,
            `reason` enum('harassment','spam','scam','inappropriate','safety','other') NOT NULL,
            `details` varchar(1000) DEFAULT NULL,
            `status` enum('open','reviewing','resolved','dismissed') NOT NULL DEFAULT 'open',
            `admin_note` varchar(1000) DEFAULT NULL,
            `handled_by` int(11) DEFAULT NULL,
            `handled_at` datetime DEFAULT NULL,
            `created_at` datetime NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `idx_reports_status` (`status`, `created_at`),
            KEY `idx_reports_reported` (`reported_user_id`),
            CONSTRAINT `chk_report_not_self` CHECK (`reporter_id` <> `reported_user_id`),
            CONSTRAINT `reports_reporter_fk` FOREIGN KEY (`reporter_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
            CONSTRAINT `reports_reported_fk` FOREIGN KEY (`reported_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
            CONSTRAINT `reports_request_fk` FOREIGN KEY (`request_id`) REFERENCES `requests` (`id`) ON DELETE SET NULL,
            CONSTRAINT `reports_message_fk` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE SET NULL,
            CONSTRAINT `reports_handler_fk` FOREIGN KEY (`handled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
        ) {$options}");

        $this->db->query('ALTER TABLE `messages` ADD KEY `idx_messages_pair_cursor` (`receiver_id`, `sender_id`, `id`)');
    }

    public function down(): void
    {
        $this->db->query('ALTER TABLE `messages` DROP KEY `idx_messages_pair_cursor`');
        $this->db->query('DROP TABLE IF EXISTS `user_reports`');
        $this->db->query('DROP TABLE IF EXISTS `user_blocks`');
    }
}

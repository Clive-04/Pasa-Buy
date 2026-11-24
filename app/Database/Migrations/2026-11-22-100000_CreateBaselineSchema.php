<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Baseline schema: reproduces the pasa_buy_db structure exactly as exported
 * from the original MariaDB 10.4 database on 2026-05-01 (schema only).
 *
 * Every table uses CREATE TABLE IF NOT EXISTS so that:
 *  - a fresh database gets the original structure, and
 *  - an existing pre-migration database is left untouched by this step.
 *
 * All structural improvements live in later migrations so that both paths
 * converge on the same schema.
 */
class CreateBaselineSchema extends Migration
{
    public function up(): void
    {
        $tableOptions = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci';

        $this->db->query("CREATE TABLE IF NOT EXISTS `users` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `first_name` varchar(20) NOT NULL,
            `last_name` varchar(20) NOT NULL,
            `email` varchar(50) NOT NULL,
            `phone_number` varchar(20) NOT NULL,
            `password` varchar(255) NOT NULL,
            `address` varchar(255) DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            `username` varchar(100) DEFAULT NULL,
            `about_me` text DEFAULT NULL,
            `profile_photo` varchar(255) DEFAULT NULL,
            `wallet_balance` decimal(10,2) NOT NULL DEFAULT 0.00,
            `role` varchar(20) DEFAULT 'user',
            PRIMARY KEY (`id`),
            UNIQUE KEY `email` (`email`),
            UNIQUE KEY `phone_number` (`phone_number`)
        ) {$tableOptions}");

        $this->db->query("CREATE TABLE IF NOT EXISTS `requests` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) NOT NULL,
            `item_name` varchar(255) NOT NULL,
            `category` enum('food','medicine','school_supplies','household','personal_care','other') NOT NULL,
            `quantity` int(11) DEFAULT 1,
            `brand` varchar(255) DEFAULT NULL,
            `item_notes` text DEFAULT NULL,
            `store_name` varchar(255) NOT NULL,
            `alt_store` varchar(255) DEFAULT NULL,
            `store_address` text NOT NULL,
            `delivery_address` text NOT NULL,
            `contact_number` varchar(20) NOT NULL,
            `landmark` varchar(255) DEFAULT NULL,
            `delivery_notes` text DEFAULT NULL,
            `estimated_price` decimal(10,2) DEFAULT 0.00,
            `service_fee` decimal(10,2) DEFAULT 0.00,
            `payment_method` enum('cod','gcash','maya','bank_transfer') NOT NULL,
            `payment_status` varchar(30) NOT NULL DEFAULT 'unpaid',
            `paid_at` datetime DEFAULT NULL,
            `urgency` enum('normal','urgent','asap') NOT NULL,
            `status` enum('pending','accepted','on_the_way','delivered') NOT NULL DEFAULT 'pending',
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            `total_amount` decimal(10,2) DEFAULT 0.00,
            `tax` decimal(10,2) DEFAULT 0.00,
            PRIMARY KEY (`id`),
            KEY `idx_user_status` (`user_id`,`status`),
            KEY `idx_category` (`category`),
            KEY `idx_urgency` (`urgency`),
            KEY `idx_payment_method` (`payment_method`),
            CONSTRAINT `requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) {$tableOptions}");

        $this->db->query("CREATE TABLE IF NOT EXISTS `accepted_requests` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `request_id` int(11) NOT NULL,
            `runner_id` int(11) NOT NULL,
            `status` enum('accepted','on_the_way','delivered') NOT NULL DEFAULT 'accepted',
            `accepted_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            UNIQUE KEY `request_id` (`request_id`),
            KEY `idx_request_runner` (`request_id`,`runner_id`),
            KEY `idx_runner_status` (`runner_id`,`status`),
            CONSTRAINT `accepted_requests_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `requests` (`id`) ON DELETE CASCADE,
            CONSTRAINT `accepted_requests_ibfk_2` FOREIGN KEY (`runner_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) {$tableOptions}");

        $this->db->query("CREATE TABLE IF NOT EXISTS `messages` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `sender_id` int(11) NOT NULL,
            `receiver_id` int(11) NOT NULL,
            `request_id` int(11) DEFAULT NULL,
            `message` text NOT NULL,
            `attachment_name` varchar(255) DEFAULT NULL,
            `attachment_path` varchar(255) DEFAULT NULL,
            `attachment_type` varchar(100) DEFAULT NULL,
            `created_at` datetime NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `sender_id` (`sender_id`),
            KEY `receiver_id` (`receiver_id`),
            KEY `request_id` (`request_id`),
            CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
            CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
            CONSTRAINT `messages_ibfk_3` FOREIGN KEY (`request_id`) REFERENCES `requests` (`id`) ON DELETE SET NULL
        ) {$tableOptions}");

        $this->db->query("CREATE TABLE IF NOT EXISTS `notifications` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) NOT NULL,
            `sender_id` int(11) DEFAULT NULL,
            `message_id` int(11) DEFAULT NULL,
            `type` varchar(50) NOT NULL,
            `is_read` tinyint(1) DEFAULT 0,
            `created_at` datetime DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `user_id` (`user_id`),
            KEY `sender_id` (`sender_id`),
            KEY `message_id` (`message_id`),
            CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
            CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
            CONSTRAINT `notifications_ibfk_3` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE SET NULL
        ) {$tableOptions}");

        $this->db->query("CREATE TABLE IF NOT EXISTS `payments` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) NOT NULL,
            `request_id` int(11) DEFAULT NULL,
            `receiver_id` int(11) DEFAULT NULL,
            `amount` decimal(10,2) NOT NULL,
            `type` enum('credit','debit') NOT NULL,
            `source` varchar(30) DEFAULT NULL,
            `status` varchar(30) NOT NULL DEFAULT 'completed',
            `reference_no` varchar(100) DEFAULT NULL,
            `description` varchar(255) DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `idx_user_type` (`user_id`,`type`),
            CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) {$tableOptions}");

        $this->db->query("CREATE TABLE IF NOT EXISTS `referrals` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) NOT NULL,
            `friend_name` varchar(100) NOT NULL,
            `friend_email` varchar(150) NOT NULL,
            `status` varchar(50) DEFAULT 'pending',
            `created_at` datetime DEFAULT current_timestamp(),
            `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `fk_referrals_user` (`user_id`),
            CONSTRAINT `fk_referrals_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) {$tableOptions}");

        $this->db->query("CREATE TABLE IF NOT EXISTS `request_notifications` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) NOT NULL,
            `sender_id` int(11) DEFAULT NULL,
            `request_id` int(11) NOT NULL,
            `type` varchar(50) NOT NULL,
            `title` varchar(255) NOT NULL,
            `content` text NOT NULL,
            `is_read` tinyint(1) NOT NULL DEFAULT 0,
            `created_at` datetime NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `user_id` (`user_id`),
            KEY `sender_id` (`sender_id`),
            KEY `request_id` (`request_id`),
            CONSTRAINT `request_notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
            CONSTRAINT `request_notifications_ibfk_2` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
            CONSTRAINT `request_notifications_ibfk_3` FOREIGN KEY (`request_id`) REFERENCES `requests` (`id`) ON DELETE CASCADE
        ) {$tableOptions}");

        $this->db->query("CREATE TABLE IF NOT EXISTS `user_ratings` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `rater_id` int(11) NOT NULL,
            `rated_user_id` int(11) NOT NULL,
            `rating` tinyint(4) NOT NULL,
            `feedback` text DEFAULT NULL,
            `created_at` datetime DEFAULT current_timestamp(),
            `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_user_rating` (`rater_id`,`rated_user_id`),
            KEY `fk_user_ratings_rated` (`rated_user_id`),
            CONSTRAINT `fk_user_ratings_rated` FOREIGN KEY (`rated_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_user_ratings_rater` FOREIGN KEY (`rater_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) {$tableOptions}");

        $this->db->query("CREATE TABLE IF NOT EXISTS `website_ratings` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) NOT NULL,
            `rating` tinyint(4) NOT NULL,
            `feedback` text DEFAULT NULL,
            `created_at` datetime DEFAULT current_timestamp(),
            `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `fk_website_ratings_user` (`user_id`),
            CONSTRAINT `fk_website_ratings_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) {$tableOptions}");
    }

    public function down(): void
    {
        // Drops every application table. Only ever run this against a
        // disposable database: it destroys all data.
        $this->db->query('SET FOREIGN_KEY_CHECKS = 0');

        foreach ([
            'website_ratings', 'user_ratings', 'request_notifications', 'referrals',
            'payments', 'notifications', 'messages', 'accepted_requests', 'requests', 'users',
        ] as $table) {
            $this->db->query("DROP TABLE IF EXISTS `{$table}`");
        }

        $this->db->query('SET FOREIGN_KEY_CHECKS = 1');
    }
}

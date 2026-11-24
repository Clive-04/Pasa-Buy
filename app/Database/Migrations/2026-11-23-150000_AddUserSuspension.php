<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Lets an administrator suspend an account. A suspended user cannot log in,
 * an open session ends on its next request (AuthFilter), and nobody can send
 * them new chat messages. Nothing is deleted: requests, payments, messages
 * and reports stay as they are, and unsuspending restores access.
 *
 * down() drops the columns: every account becomes active again.
 */
class AddUserSuspension extends Migration
{
    public function up(): void
    {
        $this->db->query("ALTER TABLE `users`
            ADD COLUMN `status` enum('active','suspended') NOT NULL DEFAULT 'active' AFTER `role`,
            ADD COLUMN `suspended_at` datetime NULL DEFAULT NULL AFTER `status`,
            ADD COLUMN `suspension_reason` varchar(255) NULL DEFAULT NULL AFTER `suspended_at`,
            ADD COLUMN `suspended_by` int(11) NULL DEFAULT NULL AFTER `suspension_reason`,
            ADD KEY `idx_users_status` (`status`)");
    }

    public function down(): void
    {
        $this->db->query('ALTER TABLE `users`
            DROP KEY `idx_users_status`,
            DROP COLUMN `suspended_by`,
            DROP COLUMN `suspension_reason`,
            DROP COLUMN `suspended_at`,
            DROP COLUMN `status`');
    }
}

<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * "Delete chat" was stored to the second, which went wrong both ways:
 *  - built-in chat hid by `created_at > deleted_at`, so a reply arriving in
 *    the same second as the deletion stayed hidden;
 *  - TalkJS compares millisecond timestamps, so messages from the last
 *    second before the deletion came back.
 *
 * Now the built-in chat records the newest message id at deletion time and
 * hides by id (exact), and deleted_at keeps milliseconds for TalkJS.
 * Rows from before this migration keep working with the old time rule.
 *
 * down() drops the column and the millisecond precision (deletions stay).
 */
class PreciseChatDeletions extends Migration
{
    public function up(): void
    {
        $this->db->query('ALTER TABLE `chat_deletions`
            MODIFY `deleted_at` datetime(3) NOT NULL,
            ADD COLUMN `last_message_id` int(11) NULL DEFAULT NULL AFTER `deleted_at`');
    }

    public function down(): void
    {
        $this->db->query('ALTER TABLE `chat_deletions`
            DROP COLUMN `last_message_id`,
            MODIFY `deleted_at` datetime NOT NULL');
    }
}

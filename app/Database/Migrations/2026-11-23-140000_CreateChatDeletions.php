<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * "Delete chat" for one person (like "delete for me" in phone messengers):
 * the chat and every message up to `deleted_at` disappear from that user's
 * view. Nothing is removed for the other person, and the messages stay
 * stored so reports and admin reviews keep their evidence. A new message
 * after `deleted_at` brings the chat back, showing only the new messages.
 *
 * request_id = 0 means "the whole conversation with that person" (built-in
 * chat lists one thread per person); TalkJS chats are per errand.
 *
 * down() drops the table: deleted chats reappear for everyone.
 */
class CreateChatDeletions extends Migration
{
    public function up(): void
    {
        $this->db->query("CREATE TABLE `chat_deletions` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) NOT NULL,
            `other_user_id` int(11) NOT NULL,
            `request_id` int(11) NOT NULL DEFAULT 0,
            `deleted_at` datetime NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_chat_deletion` (`user_id`, `other_user_id`, `request_id`),
            CONSTRAINT `chat_del_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
            CONSTRAINT `chat_del_other_fk` FOREIGN KEY (`other_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    }

    public function down(): void
    {
        $this->db->query('DROP TABLE IF EXISTS `chat_deletions`');
    }
}

<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Ratings are given per completed delivery instead of once per pair of
 * users: each rating belongs to a request, a participant can rate a given
 * request once, and every delivery adds to the person's average.
 * Older ratings keep request_id NULL and still count.
 */
class RatePerDelivery extends Migration
{
    public function up(): void
    {
        $this->db->query('ALTER TABLE `user_ratings`
            ADD `request_id` INT(11) NULL DEFAULT NULL AFTER `rated_user_id`,
            ADD UNIQUE KEY `uq_rating_per_request` (`rater_id`, `request_id`),
            ADD CONSTRAINT `fk_user_ratings_request` FOREIGN KEY (`request_id`) REFERENCES `requests` (`id`) ON DELETE CASCADE');

        // The new key starts with rater_id, so it now backs the rater foreign key.
        $this->db->query('ALTER TABLE `user_ratings` DROP INDEX `uq_user_rating`');
    }

    public function down(): void
    {
        // Back to one rating per pair: keep each pair's most recent rating.
        $this->db->query('DELETE r1 FROM `user_ratings` r1
            JOIN `user_ratings` r2 ON r1.rater_id = r2.rater_id AND r1.rated_user_id = r2.rated_user_id AND r1.id < r2.id');
        $this->db->query('ALTER TABLE `user_ratings` ADD UNIQUE KEY `uq_user_rating` (`rater_id`, `rated_user_id`)');
        $this->db->query('ALTER TABLE `user_ratings`
            DROP FOREIGN KEY `fk_user_ratings_request`,
            DROP INDEX `uq_rating_per_request`,
            DROP COLUMN `request_id`');
    }
}

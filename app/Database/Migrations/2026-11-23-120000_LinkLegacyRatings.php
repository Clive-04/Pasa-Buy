<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Ratings made before per-delivery ratings were one per pair of users. Link
 * each to the most recent delivered request between that pair, so that
 * delivery shows as already rated. Ratings with no matching delivery stay
 * unlinked and still count toward the average.
 */
class LinkLegacyRatings extends Migration
{
    public function up(): void
    {
        $this->db->query("UPDATE `user_ratings` ur
            SET ur.request_id = (
                SELECT r.id FROM `requests` r
                JOIN `accepted_requests` ar ON ar.request_id = r.id
                WHERE r.status = 'delivered'
                  AND ((r.user_id = ur.rater_id AND ar.runner_id = ur.rated_user_id)
                    OR (r.user_id = ur.rated_user_id AND ar.runner_id = ur.rater_id))
                ORDER BY r.updated_at DESC, r.id DESC
                LIMIT 1
            )
            WHERE ur.request_id IS NULL");
    }

    public function down(): void
    {
        // Nothing to undo: the previous migration's rollback drops request_id.
    }
}

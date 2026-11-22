<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\Exceptions\DatabaseException;

/**
 * One rating per participant per delivered request. Ratings stack: every
 * completed errand adds to the other person's average.
 */
class RatingService
{
    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    /**
     * The rating $raterId gave for $requestId, or null.
     *
     * @return array<string, mixed>|null
     */
    public function ratingFor(int $raterId, int $requestId): ?array
    {
        return $this->db->table('user_ratings')
            ->where('rater_id', $raterId)->where('request_id', $requestId)
            ->get()->getRowArray();
    }

    /** Rates the other participant of a delivered request. Returns the rated user's id. */
    public function rateDelivery(int $raterId, int $requestId, int $rating, string $feedback): int
    {
        if ($rating < 1 || $rating > 5) {
            throw new ServiceException('Please choose a rating from 1 to 5 stars.');
        }

        $feedback = trim($feedback);

        if (mb_strlen($feedback) > 1000) {
            throw new ServiceException('Feedback must be at most 1000 characters.');
        }

        $errand = $this->db->table('requests r')
            ->select('r.user_id AS requester_id, ar.runner_id, r.status')
            ->join('accepted_requests ar', 'ar.request_id = r.id')
            ->where('r.id', $requestId)
            ->get()->getRowArray();

        if ($errand === null || $errand['status'] !== 'delivered') {
            throw new ServiceException('You can rate after the request has been delivered.');
        }

        $ratedId = match ($raterId) {
            (int) $errand['requester_id'] => (int) $errand['runner_id'],
            (int) $errand['runner_id']    => (int) $errand['requester_id'],
            default                       => throw new ServiceException('Only the requester and the runner can rate this errand.'),
        };

        if ($this->ratingFor($raterId, $requestId) !== null) {
            throw new ServiceException('You already rated this delivery.');
        }

        try {
            $saved = $this->db->table('user_ratings')->insert([
                'rater_id'      => $raterId,
                'rated_user_id' => $ratedId,
                'request_id'    => $requestId,
                'rating'        => $rating,
                'feedback'      => $feedback !== '' ? $feedback : null,
            ]);
        } catch (DatabaseException) {
            $saved = false;
        }

        if (! $saved) {
            // Unique (rater_id, request_id): a double submit lost the race.
            throw new ServiceException('You already rated this delivery.');
        }

        return $ratedId;
    }
}

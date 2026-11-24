<?php

namespace App\Services;

use App\Libraries\Money;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\Exceptions\DatabaseException;
use Throwable;

/**
 * Shared plumbing for operations that must be all-or-nothing.
 *
 * Locking order (prevents deadlocks between operations):
 *   1. the request row, 2. its accepted_requests row, 3. user rows in
 *   ascending id order. Every service follows this order.
 */
abstract class TransactionalService
{
    protected BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    /**
     * Runs $work in a database transaction. Commits only if $work returns
     * normally; any exception rolls back every write made inside it.
     *
     * @template T
     *
     * @param callable(): T $work
     *
     * @return T
     */
    protected function transaction(callable $work): mixed
    {
        // By default CodeIgniter does NOT throw on a failed query inside a
        // transaction; it only marks the transaction failed and carries on.
        // transException(true) makes the first failure roll back and throw
        // immediately, so no later statement runs on a failed transaction.
        // CodeIgniter's strict mode keeps transStatus false after any failed
        // transaction on this connection, which would make every later,
        // unrelated transaction fail too. Each service call is independent.
        $this->db->resetTransStatus();
        $this->db->transException(true);
        $this->db->transBegin();

        try {
            $result = $work();

            if ($this->db->transStatus() === false) {
                throw new ServiceException('The operation could not be completed. Please try again.');
            }

            $this->db->transCommit();

            return $result;
        } catch (Throwable $e) {
            $this->db->transRollback(); // no-op if the framework already rolled back

            throw $this->translate($e);
        } finally {
            $this->db->transException(false);
        }
    }

    /** Maps low-level database failures to user-safe messages. */
    protected function translate(Throwable $e): Throwable
    {
        if (! $e instanceof DatabaseException) {
            return $e;
        }

        return match ((int) $e->getCode()) {
            1062    => new ServiceException('This action was already completed.', 1062, $e),
            1205, 1213 => new ServiceException('The system is busy with another update to this item. Please try again.', (int) $e->getCode(), $e),
            4025, 3819 => new ServiceException('That change would make a balance or amount invalid.', (int) $e->getCode(), $e),
            default => $e,
        };
    }

    /** SELECT … FOR UPDATE on one row; the lock is held until commit/rollback. */
    protected function lockRow(string $table, int $id): ?array
    {
        return $this->db->query("SELECT * FROM `{$table}` WHERE `id` = ? FOR UPDATE", [$id])->getRowArray();
    }

    protected function lockAcceptance(int $requestId): ?array
    {
        return $this->db->query('SELECT * FROM `accepted_requests` WHERE `request_id` = ? FOR UPDATE', [$requestId])->getRowArray();
    }

    /**
     * Locks several users in ascending id order.
     *
     * @return array<int, array<string, mixed>> keyed by user id
     */
    protected function lockUsers(int ...$ids): array
    {
        $ids = array_values(array_unique($ids));
        sort($ids);
        $users = [];

        foreach ($ids as $id) {
            $user = $this->lockRow('users', $id);

            if ($user === null) {
                throw new ServiceException('Account not found.');
            }

            $users[$id] = $user;
        }

        return $users;
    }

    /**
     * Records one wallet movement and updates the cached balance.
     * Returns the new balance in centavos.
     */
    protected function postLedgerEntry(
        int $userId,
        int $currentBalanceCents,
        string $type,
        string $direction,
        int $amountCents,
        string $source,
        string $description,
        ?int $requestId = null,
        ?int $counterpartyId = null,
        ?string $idempotencyKey = null,
    ): int {
        if ($amountCents <= 0) {
            throw new ServiceException('Amount must be greater than zero.');
        }

        $newBalance = $direction === 'credit'
            ? $currentBalanceCents + $amountCents
            : $currentBalanceCents - $amountCents;

        if ($newBalance < 0) {
            throw new ServiceException('Insufficient wallet balance.');
        }

        $this->db->table('users')
            ->where('id', $userId)
            ->update(['wallet_balance' => Money::toDecimal($newBalance)]);

        $this->db->table('payments')->insert([
            'user_id'         => $userId,
            'request_id'      => $requestId,
            'receiver_id'     => $counterpartyId,
            'amount'          => Money::toDecimal($amountCents),
            'balance_after'   => Money::toDecimal($newBalance),
            'type'            => $type,
            'direction'       => $direction,
            'source'          => $source,
            'status'          => 'completed',
            'reference_no'    => null,
            'idempotency_key' => $idempotencyKey,
            'description'     => $description,
            'created_at'      => date('Y-m-d H:i:s'),
        ]);

        return $newBalance;
    }

    protected function notify(int $userId, ?int $senderId, int $requestId, string $type, string $title, string $content): void
    {
        $this->db->table('request_notifications')->insert([
            'user_id'    => $userId,
            'sender_id'  => $senderId,
            'request_id' => $requestId,
            'type'       => $type,
            'title'      => $title,
            'content'    => $content,
            'is_read'    => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    protected function displayName(array $user): string
    {
        return trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: 'A user';
    }
}

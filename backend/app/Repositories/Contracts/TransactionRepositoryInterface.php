<?php

namespace App\Repositories\Contracts;

use App\Models\Transaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface TransactionRepositoryInterface
{
    /**
     * Create a new transaction
     */
    public function create(array $data): Transaction;

    /**
     * Find transaction by UUID
     */
    public function findByUuid(string $uuid): ?Transaction;

    /**
     * Find transaction by idempotency key
     */
    public function findByIdempotencyKey(string $idempotencyKey): ?Transaction;

    /**
     * Get paginated transactions for a user
     */
    public function getPaginatedForUser(
        int $userId,
        int $perPage = 20,
        ?string $direction = null
    ): LengthAwarePaginator;

    /**
     * Get recent transactions for a user
     */
    public function getRecentForUser(int $userId, int $limit = 10): Collection;

    /**
     * Get transaction statistics for a user
     */
    public function getStatsForUser(int $userId): array;

    /**
     * Count transactions sent by user
     */
    public function countSentByUser(int $userId): int;

    /**
     * Count transactions received by user
     */
    public function countReceivedByUser(int $userId): int;

    /**
     * Sum total sent by user (including commission)
     */
    public function sumTotalSentByUser(int $userId): int;

    /**
     * Sum total received by user
     */
    public function sumTotalReceivedByUser(int $userId): int;
}

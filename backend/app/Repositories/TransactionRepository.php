<?php

namespace App\Repositories;

use App\Models\Transaction;
use App\Repositories\Contracts\TransactionRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class TransactionRepository implements TransactionRepositoryInterface
{
    /**
     * Create a new transaction
     */
    public function create(array $data): Transaction
    {
        return Transaction::create($data);
    }

    /**
     * Find transaction by UUID
     */
    public function findByUuid(string $uuid): ?Transaction
    {
        return Transaction::where('uuid', $uuid)
            ->with(['sender:id,name,email', 'receiver:id,name,email'])
            ->first();
    }

    /**
     * Find transaction by idempotency key
     */
    public function findByIdempotencyKey(string $idempotencyKey): ?Transaction
    {
        return Transaction::where('idempotency_key', $idempotencyKey)->first();
    }

    /**
     * Get paginated transactions for a user
     */
    public function getPaginatedForUser(int $userId, int $perPage = 20, ?string $direction = null): LengthAwarePaginator
    {
        $query = Transaction::query()
            ->where(function ($q) use ($userId, $direction) {
                if ($direction === 'in') {
                    $q->where('receiver_id', $userId);
                } elseif ($direction === 'out') {
                    $q->where('sender_id', $userId);
                } else {
                    $q->where('sender_id', $userId)
                        ->orWhere('receiver_id', $userId);
                }
            })
            ->with(['sender:id,name,email', 'receiver:id,name,email'])
            ->orderBy('created_at', 'desc');

        return $query->paginate($perPage);
    }

    /**
     * Get recent transactions for a user
     */
    public function getRecentForUser(int $userId, int $limit = 10): Collection
    {
        return Transaction::where(function ($q) use ($userId) {
            $q->where('sender_id', $userId)
                ->orWhere('receiver_id', $userId);
        })
            ->with(['sender:id,name,email', 'receiver:id,name,email'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get transaction statistics for a user
     */
    public function getStatsForUser(int $userId): array
    {
        $countSent = $this->countSentByUser($userId);
        $countReceived = $this->countReceivedByUser($userId);

        return [
            'total_sent' => $this->sumTotalSentByUser($userId), // Amount in cents (amount + commission)
            'total_received' => $this->sumTotalReceivedByUser($userId), // Amount in cents
            'total_transactions' => $countSent + $countReceived, // Count
            'total_commission' => $this->sumTotalCommissionByUser($userId), // Amount in cents
        ];
    }

    /**
     * Count transactions sent by user
     */
    public function countSentByUser(int $userId): int
    {
        return Transaction::where('sender_id', $userId)->count();
    }

    /**
     * Count transactions received by user
     */
    public function countReceivedByUser(int $userId): int
    {
        return Transaction::where('receiver_id', $userId)->count();
    }

    /**
     * Sum total sent by user (including commission)
     */
    public function sumTotalSentByUser(int $userId): int
    {
        return Transaction::where('sender_id', $userId)
            ->selectRaw('SUM(amount + commission) as total')
            ->value('total') ?? 0;
    }

    /**
     * Sum total received by user
     */
    public function sumTotalReceivedByUser(int $userId): int
    {
        return Transaction::where('receiver_id', $userId)->sum('amount');
    }

    /**
     * Sum total commission paid by user
     */
    public function sumTotalCommissionByUser(int $userId): int
    {
        return Transaction::where('sender_id', $userId)->sum('commission');
    }
}

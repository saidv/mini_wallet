<?php

namespace App\Repositories\Contracts;

use App\Models\User;
use Illuminate\Support\Collection;

interface UserRepositoryInterface
{
    /**
     * Find user by ID
     */
    public function findById(int $id): ?User;

    /**
     * Find user by email
     */
    public function findByEmail(string $email): ?User;

    /**
     * Find multiple users by IDs
     */
    public function findByIds(array $ids): Collection;

    /**
     * Update user balance
     */
    public function updateBalance(int $userId, int $newBalance): bool;

    /**
     * Lock user for update (for transactions)
     */
    public function lockForUpdate(int $userId): ?User;
}

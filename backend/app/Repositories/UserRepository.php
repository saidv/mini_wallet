<?php

namespace App\Repositories;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Collection;

class UserRepository implements UserRepositoryInterface
{
    /**
     * Find user by ID
     */
    public function findById(int $id): ?User
    {
        return User::find($id);
    }

    /**
     * Find user by email
     */
    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    /**
     * Find multiple users by IDs
     */
    public function findByIds(array $ids): Collection
    {
        return User::whereIn('id', $ids)->get();
    }

    /**
     * Update user balance
     */
    public function updateBalance(int $userId, int $newBalance): bool
    {
        return User::where('id', $userId)->update(['balance' => $newBalance]);
    }

    /**
     * Lock user for update (for transactions)
     */
    public function lockForUpdate(int $userId): ?User
    {
        return User::where('id', $userId)->lockForUpdate()->first();
    }
}

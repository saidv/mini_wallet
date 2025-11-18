<?php

namespace App\Services;

use App\Jobs\ProcessTransactionOutbox;
use App\Models\BalanceSnapshot;
use App\Models\Transaction;
use App\Models\TransactionOutbox;
use App\Models\User;
use App\Repositories\Contracts\TransactionRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Facades\DB;

class TransferService
{
    /**
     * Maximum transaction retry attempts on deadlock.
     */
    private const MAX_RETRIES = 3;

    /**
     * Commission rate (1.5%).
     */
    private const COMMISSION_RATE = 0.015;

    /**
     * Construct a new instance of TransferService.
     *
     * @param UserRepositoryInterface $userRepository
     * @param TransactionRepositoryInterface $transactionRepository
     */
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private TransactionRepositoryInterface $transactionRepository
    ) {
    }

    /**
     * Transfer money from sender to receiver.
     *
     * @param  int  $amount  Amount in cents
     *
     * @throws \Exception
     */
    public function transfer(
        int $sender_id,
        int $receiver_id,
        int $amount,
        string $idempotency_key,
        array $metadata = []
    ): Transaction {
        $this->validateTransferInputs($sender_id, $receiver_id, $amount);

        // Calculate commission (rounded UP to prevent micro-losses)
        $commission = (int) ceil($amount * self::COMMISSION_RATE);
        $total_debited = $amount + $commission;

        $attempt_count = 0;
        $last_exception = null;

        while ($attempt_count < self::MAX_RETRIES) {
            $attempt_count++;

            try {
                return DB::transaction(function () use (
                    $sender_id,
                    $receiver_id,
                    $amount,
                    $commission,
                    $total_debited,
                    $idempotency_key,
                    $metadata
                ) {
                    $existing_transaction = Transaction::where('idempotency_key', $idempotency_key)
                        ->lockForUpdate()
                        ->first();

                    if ($existing_transaction) {
                        return $existing_transaction;
                    }

                    // Deterministic lock ordering (prevent deadlocks)
                    $lock_order = [$sender_id, $receiver_id];
                    sort($lock_order);

                    $locked_users = User::whereIn('id', $lock_order)
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->get()
                        ->keyBy('id');

                    $sender = $locked_users->get($sender_id);
                    $receiver = $locked_users->get($receiver_id);

                    // Verify users exist
                    if (! $sender || ! $receiver) {
                        throw new \Exception('Sender or receiver not found');
                    }

                    // Check sufficient balance
                    if ($sender->balance < $total_debited) {
                        throw new \Exception('Insufficient balance');
                    }

                    // Update balances
                    $sender->balance -= $total_debited;
                    $receiver->balance += $amount;

                    $sender->save();
                    $receiver->save();

                    // Create transaction record
                    $transaction = Transaction::create([
                        'sender_id' => $sender_id,
                        'receiver_id' => $receiver_id,
                        'amount' => $amount,
                        'commission' => $commission,
                        'status' => 'completed',
                        'idempotency_key' => $idempotency_key,
                        'metadata' => $metadata,
                    ]);

                    BalanceSnapshot::create([
                        'user_id' => $sender_id,
                        'balance' => $sender->balance,
                        'transaction_uuid' => $transaction->uuid,
                    ]);

                    BalanceSnapshot::create([
                        'user_id' => $receiver_id,
                        'balance' => $receiver->balance,
                        'transaction_uuid' => $transaction->uuid,
                    ]);

                    // Create outbox entries for reliable event delivery
                    $outbox = TransactionOutbox::create([
                        'transaction_uuid' => $transaction->uuid,
                        'event_type' => 'money.transferred',
                        'payload' => [
                            'transaction_uuid' => $transaction->uuid,
                            'sender_id' => $sender_id,
                            'receiver_id' => $receiver_id,
                            'amount' => $amount,
                            'commission' => $commission,
                            'sender_balance' => $sender->balance,
                            'receiver_balance' => $receiver->balance,
                        ],
                        'status' => 'pending',
                    ]);

                    // Dispatch job to process outbox AFTER transaction commits
                    ProcessTransactionOutbox::dispatch($outbox->id)
                        ->onQueue('events')
                        ->afterCommit();

                    return $transaction;
                });
            } catch (\Illuminate\Database\QueryException $e) {
                $last_exception = $e;

                // Retry on deadlock
                if ($this->isDeadlock($e) && $attempt_count < self::MAX_RETRIES) {
                    usleep(100000 * $attempt_count); // Exponential backoff

                    continue;
                }

                throw $e;
            }
        }

        throw new \Exception(
            'Transaction failed after ' . self::MAX_RETRIES . ' attempts due to deadlock',
            0,
            $last_exception
        );
    }

    /**
     * Validate transfer inputs.
     *
     * @throws \Exception
     */
    private function validateTransferInputs(int $sender_id, int $receiver_id, int $amount): void
    {
        if ($sender_id === $receiver_id) {
            throw new \Exception('Cannot transfer to yourself');
        }

        if ($amount <= 0) {
            throw new \Exception('Amount must be positive');
        }
    }

    /**
     * Check if exception is a deadlock error.
     */
    private function isDeadlock(\Illuminate\Database\QueryException $e): bool
    {
        return in_array($e->getCode(), ['40001', '1213']);
    }

    /**
     * Generate idempotency key from request data.
     */
    public static function generateIdempotencyKey(
        int $sender_id,
        int $receiver_id,
        int $amount,
        string $timestamp
    ): string {
        return hash('sha256', implode('|', [$sender_id, $receiver_id, $amount, $timestamp]));
    }
}

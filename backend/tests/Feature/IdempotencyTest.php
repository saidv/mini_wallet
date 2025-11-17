<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Models\User;
use App\Services\TransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IdempotencyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that idempotency key prevents duplicate transactions.
     */
    public function test_idempotency_key_prevents_duplicate_transactions(): void
    {
        // Create test users
        $sender = User::factory()->create(['balance' => 100000, 'initial_balance' => 100000]);
        $receiver = User::factory()->create(['balance' => 0, 'initial_balance' => 0]);

        $transfer_service = new TransferService(
            app(\App\Repositories\Contracts\UserRepositoryInterface::class),
            app(\App\Repositories\Contracts\TransactionRepositoryInterface::class)
        );
        $idempotency_key = 'test-key-'.now()->timestamp;
        $amount = 5000;

        // First transfer
        $transaction1 = $transfer_service->transfer(
            sender_id: $sender->id,
            receiver_id: $receiver->id,
            amount: $amount,
            idempotency_key: $idempotency_key
        );

        // Second transfer with same idempotency key
        $transaction2 = $transfer_service->transfer(
            sender_id: $sender->id,
            receiver_id: $receiver->id,
            amount: $amount,
            idempotency_key: $idempotency_key
        );

        // Should return the same transaction
        $this->assertEquals($transaction1->uuid, $transaction2->uuid);

        // Check only one transaction was created
        $this->assertEquals(1, Transaction::count());

        // Verify balances were only debited once
        $sender->refresh();
        $receiver->refresh();

        $commission = (int) ceil($amount * 0.015);
        $total_debited = $amount + $commission;

        $this->assertEquals(100000 - $total_debited, $sender->balance);
        $this->assertEquals($amount, $receiver->balance);
    }

    /**
     * Test concurrent requests with same idempotency key.
     */
    public function test_concurrent_requests_with_same_idempotency_key(): void
    {
        // Create test users
        $sender = User::factory()->create(['balance' => 100000, 'initial_balance' => 100000]);
        $receiver = User::factory()->create(['balance' => 0, 'initial_balance' => 0]);

        $transfer_service = new TransferService(
            app(\App\Repositories\Contracts\UserRepositoryInterface::class),
            app(\App\Repositories\Contracts\TransactionRepositoryInterface::class)
        );
        $idempotency_key = 'concurrent-test-'.now()->timestamp;
        $amount = 5000;

        $results = [];
        $exceptions = [];

        // Simulate concurrent requests (100 requests with same idempotency key)
        for ($i = 0; $i < 100; $i++) {
            try {
                $transaction = $transfer_service->transfer(
                    sender_id: $sender->id,
                    receiver_id: $receiver->id,
                    amount: $amount,
                    idempotency_key: $idempotency_key
                );
                $results[] = $transaction->uuid;
            } catch (\Exception $e) {
                $exceptions[] = $e->getMessage();
            }
        }

        // All results should have the same UUID
        $unique_uuids = array_unique($results);
        $this->assertCount(1, $unique_uuids, 'Multiple transactions created with same idempotency key');

        // Check only one transaction was created
        $this->assertEquals(1, Transaction::count());

        // Verify balances were only debited once
        $sender->refresh();
        $receiver->refresh();

        $commission = (int) ceil($amount * 0.015);
        $total_debited = $amount + $commission;

        $this->assertEquals(100000 - $total_debited, $sender->balance);
        $this->assertEquals($amount, $receiver->balance);

        // Log results
        $this->info('Concurrent test results:');
        $this->info('- Total requests: 100');
        $this->info('- Successful transactions returned: '.count($results));
        $this->info('- Unique transaction UUIDs: '.count($unique_uuids));
        $this->info('- Exceptions thrown: '.count($exceptions));
    }

    /**
     * Test different idempotency keys create separate transactions.
     */
    public function test_different_idempotency_keys_create_separate_transactions(): void
    {
        // Create test users with sufficient balance for 3 transactions
        $sender = User::factory()->create(['balance' => 100000, 'initial_balance' => 100000]);
        $receiver = User::factory()->create(['balance' => 0, 'initial_balance' => 0]);

        $transfer_service = new TransferService(
            app(\App\Repositories\Contracts\UserRepositoryInterface::class),
            app(\App\Repositories\Contracts\TransactionRepositoryInterface::class)
        );
        $amount = 5000;

        // Create 3 transactions with different idempotency keys
        $transaction1 = $transfer_service->transfer(
            sender_id: $sender->id,
            receiver_id: $receiver->id,
            amount: $amount,
            idempotency_key: 'key-1'
        );

        $transaction2 = $transfer_service->transfer(
            sender_id: $sender->id,
            receiver_id: $receiver->id,
            amount: $amount,
            idempotency_key: 'key-2'
        );

        $transaction3 = $transfer_service->transfer(
            sender_id: $sender->id,
            receiver_id: $receiver->id,
            amount: $amount,
            idempotency_key: 'key-3'
        );

        // Should have 3 different UUIDs
        $this->assertNotEquals($transaction1->uuid, $transaction2->uuid);
        $this->assertNotEquals($transaction2->uuid, $transaction3->uuid);
        $this->assertNotEquals($transaction1->uuid, $transaction3->uuid);

        // Check 3 transactions were created
        $this->assertEquals(3, Transaction::count());

        // Verify balances were debited 3 times
        $sender->refresh();
        $receiver->refresh();

        $commission = (int) ceil($amount * 0.015);
        $total_debited = $amount + $commission;

        $this->assertEquals(100000 - ($total_debited * 3), $sender->balance);
        $this->assertEquals($amount * 3, $receiver->balance);
    }

    /**
     * Helper to output info messages.
     */
    private function info(string $message): void
    {
        fwrite(STDOUT, $message."\n");
    }
}

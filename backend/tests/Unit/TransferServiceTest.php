<?php

namespace Tests\Unit;

use App\Models\Transaction;
use App\Models\User;
use App\Repositories\Contracts\TransactionRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\TransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransferServiceTest extends TestCase
{
    use RefreshDatabase;

    private $userRepository;

    private $transactionRepository;

    private $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userRepository = app(UserRepositoryInterface::class);
        $this->transactionRepository = app(TransactionRepositoryInterface::class);
        $this->service = new TransferService($this->userRepository, $this->transactionRepository);
    }

    /**
     * Test successful transfer between users.
     */
    public function test_transfer_successful(): void
    {
        $sender = User::factory()->create(['balance' => 100000]);
        $receiver = User::factory()->create(['balance' => 0]);
        $amount = 5000;
        $idempotency_key = 'unit-test-key-1';

        $transaction = $this->service->transfer(
            sender_id: $sender->id,
            receiver_id: $receiver->id,
            amount: $amount,
            idempotency_key: $idempotency_key
        );

        $commission = (int) ceil($amount * 0.015);
        $total_debited = $amount + $commission;

        $sender->refresh();
        $receiver->refresh();

        $this->assertEquals($sender->balance, 100000 - $total_debited);
        $this->assertEquals($receiver->balance, $amount);
        $this->assertEquals($transaction->amount, $amount);
        $this->assertEquals($transaction->commission, $commission);
        $this->assertEquals($transaction->idempotency_key, $idempotency_key);
    }

    /**
     * Test transfer fails with insufficient balance.
     */
    public function test_transfer_insufficient_balance(): void
    {
        $sender = User::factory()->create(['balance' => 1000]);
        $receiver = User::factory()->create(['balance' => 0]);
        $amount = 5000;
        $idempotency_key = 'unit-test-key-2';

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Insufficient balance');

        $this->service->transfer(
            sender_id: $sender->id,
            receiver_id: $receiver->id,
            amount: $amount,
            idempotency_key: $idempotency_key
        );
    }

    /**
     * Test transfer fails when sender and receiver are the same.
     */
    public function test_transfer_to_self_fails(): void
    {
        $user = User::factory()->create(['balance' => 10000]);
        $amount = 1000;
        $idempotency_key = 'unit-test-key-3';

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot transfer to yourself');

        $this->service->transfer(
            sender_id: $user->id,
            receiver_id: $user->id,
            amount: $amount,
            idempotency_key: $idempotency_key
        );
    }

    /**
     * Test transfer fails when amount is not positive.
     */
    public function test_transfer_invalid_amount_fails(): void
    {
        $sender = User::factory()->create(['balance' => 10000]);
        $receiver = User::factory()->create(['balance' => 0]);
        $amount = 0;
        $idempotency_key = 'unit-test-key-4';

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Amount must be positive');

        $this->service->transfer(
            sender_id: $sender->id,
            receiver_id: $receiver->id,
            amount: $amount,
            idempotency_key: $idempotency_key
        );
    }

    /**
     * Test idempotency: same key returns same transaction.
     */
    public function test_transfer_idempotency(): void
    {
        $sender = User::factory()->create(['balance' => 100000]);
        $receiver = User::factory()->create(['balance' => 0]);
        $amount = 5000;
        $idempotency_key = 'unit-test-key-5';

        $transaction1 = $this->service->transfer(
            sender_id: $sender->id,
            receiver_id: $receiver->id,
            amount: $amount,
            idempotency_key: $idempotency_key
        );

        $transaction2 = $this->service->transfer(
            sender_id: $sender->id,
            receiver_id: $receiver->id,
            amount: $amount,
            idempotency_key: $idempotency_key
        );

        $this->assertEquals($transaction1->uuid, $transaction2->uuid);
        $this->assertEquals(1, Transaction::where('idempotency_key', $idempotency_key)->count());
    }
}

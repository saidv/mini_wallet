<?php

namespace Tests\Feature;

use App\Models\BalanceSnapshot;
use App\Models\User;
use App\Services\TransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BalanceSnapshotTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that balance snapshots are created for both sender and receiver.
     */
    public function test_snapshots_created_for_both_parties(): void
    {
        $sender = User::factory()->create(['balance' => 100000, 'initial_balance' => 100000]);
        $receiver = User::factory()->create(['balance' => 50000, 'initial_balance' => 50000]);

        $transfer_service = new TransferService(
            app(\App\Repositories\Contracts\UserRepositoryInterface::class),
            app(\App\Repositories\Contracts\TransactionRepositoryInterface::class)
        );
        $amount = 10000;

        $transaction = $transfer_service->transfer(
            sender_id: $sender->id,
            receiver_id: $receiver->id,
            amount: $amount,
            idempotency_key: 'snapshot-test-1'
        );

        // Check 2 snapshots were created
        $this->assertEquals(2, BalanceSnapshot::count());

        // Verify sender snapshot
        $sender_snapshot = BalanceSnapshot::where('user_id', $sender->id)
            ->where('transaction_uuid', $transaction->uuid)
            ->first();

        $this->assertNotNull($sender_snapshot);

        $sender->refresh();
        $this->assertEquals($sender->balance, $sender_snapshot->balance);

        // Verify receiver snapshot
        $receiver_snapshot = BalanceSnapshot::where('user_id', $receiver->id)
            ->where('transaction_uuid', $transaction->uuid)
            ->first();

        $this->assertNotNull($receiver_snapshot);

        $receiver->refresh();
        $this->assertEquals($receiver->balance, $receiver_snapshot->balance);

        $this->info('Balance snapshots created correctly');
        $this->info("   - Sender snapshot: {$sender_snapshot->balance} cents");
        $this->info("   - Receiver snapshot: {$receiver_snapshot->balance} cents");
    }

    /**
     * Test snapshots reflect balances AFTER transaction.
     */
    public function test_snapshots_reflect_post_transaction_balances(): void
    {
        $sender = User::factory()->create(['balance' => 100000, 'initial_balance' => 100000]);
        $receiver = User::factory()->create(['balance' => 0, 'initial_balance' => 0]);

        $transfer_service = new TransferService(
            app(\App\Repositories\Contracts\UserRepositoryInterface::class),
            app(\App\Repositories\Contracts\TransactionRepositoryInterface::class)
        );
        $amount = 25000;
        $commission = (int) ceil($amount * 0.015);
        $total_debited = $amount + $commission;

        $transaction = $transfer_service->transfer(
            sender_id: $sender->id,
            receiver_id: $receiver->id,
            amount: $amount,
            idempotency_key: 'snapshot-timing-test'
        );

        $sender_snapshot = BalanceSnapshot::where('user_id', $sender->id)
            ->where('transaction_uuid', $transaction->uuid)
            ->first();

        $receiver_snapshot = BalanceSnapshot::where('user_id', $receiver->id)
            ->where('transaction_uuid', $transaction->uuid)
            ->first();

        // Snapshots should match current balances (post-transaction)
        $expected_sender_balance = 100000 - $total_debited;
        $expected_receiver_balance = $amount;

        $this->assertEquals($expected_sender_balance, $sender_snapshot->balance);
        $this->assertEquals($expected_receiver_balance, $receiver_snapshot->balance);

        $this->info('Snapshots reflect POST-transaction balances');
        $this->info("   - Sender: 100000 - {$total_debited} = {$sender_snapshot->balance}");
        $this->info("   - Receiver: 0 + {$amount} = {$receiver_snapshot->balance}");
    }

    /**
     * Test audit trail can reconstruct balance history.
     */
    public function test_audit_trail_reconstructs_balance_history(): void
    {
        $user = User::factory()->create(['balance' => 100000, 'initial_balance' => 100000]);
        $counterparty = User::factory()->create(['balance' => 100000, 'initial_balance' => 100000]);

        $transfer_service = new TransferService(
            app(\App\Repositories\Contracts\UserRepositoryInterface::class),
            app(\App\Repositories\Contracts\TransactionRepositoryInterface::class)
        );

        // Make multiple transactions
        $transactions = [
            ['amount' => 5000, 'direction' => 'out'],   // Send $50
            ['amount' => 10000, 'direction' => 'in'],   // Receive $100
            ['amount' => 3000, 'direction' => 'out'],   // Send $30
            ['amount' => 20000, 'direction' => 'in'],   // Receive $200
        ];

        $expected_balance = 100000;
        $balance_history = [$expected_balance];

        foreach ($transactions as $index => $tx) {
            if ($tx['direction'] === 'out') {
                $commission = (int) ceil($tx['amount'] * 0.015);
                $transfer_service->transfer(
                    sender_id: $user->id,
                    receiver_id: $counterparty->id,
                    amount: $tx['amount'],
                    idempotency_key: "history-test-{$index}"
                );
                $expected_balance -= ($tx['amount'] + $commission);
            } else {
                $transfer_service->transfer(
                    sender_id: $counterparty->id,
                    receiver_id: $user->id,
                    amount: $tx['amount'],
                    idempotency_key: "history-test-{$index}"
                );
                $expected_balance += $tx['amount'];
            }

            $balance_history[] = $expected_balance;
        }

        // Verify we can reconstruct history from snapshots
        $snapshots = BalanceSnapshot::where('user_id', $user->id)
            ->orderBy('created_at')
            ->get();

        $this->assertCount(4, $snapshots);

        $this->info('Balance history reconstructed from snapshots:');
        $this->info('   - Initial: 100000 cents ($1000.00)');

        foreach ($snapshots as $index => $snapshot) {
            $this->info("   - Transaction {$index}: {$snapshot->balance} cents");
        }

        // Final balance should match
        $user->refresh();
        $this->assertEquals($expected_balance, $user->balance);
        $this->assertEquals($expected_balance, $snapshots->last()->balance);
    }

    /**
     * Test snapshots are NOT created if transaction fails.
     */
    public function test_no_snapshots_created_on_transaction_failure(): void
    {
        $sender = User::factory()->create(['balance' => 100, 'initial_balance' => 100]);
        $receiver = User::factory()->create(['balance' => 0, 'initial_balance' => 0]);

        $transfer_service = new TransferService(
            app(\App\Repositories\Contracts\UserRepositoryInterface::class),
            app(\App\Repositories\Contracts\TransactionRepositoryInterface::class)
        );
        $amount = 10000; // Way more than sender has

        try {
            $transfer_service->transfer(
                sender_id: $sender->id,
                receiver_id: $receiver->id,
                amount: $amount,
                idempotency_key: 'failure-test'
            );
            $this->fail('Expected exception for insufficient balance');
        } catch (\Exception $e) {
            $this->assertStringContainsString('Insufficient balance', $e->getMessage());
        }

        // No snapshots should have been created
        $this->assertEquals(0, BalanceSnapshot::count());

        $this->info('No snapshots created on transaction failure');
    }

    /**
     * Helper to output info messages.
     */
    private function info(string $message): void
    {
        fwrite(STDOUT, $message."\n");
    }
}

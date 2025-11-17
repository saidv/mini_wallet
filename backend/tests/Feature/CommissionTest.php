<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\TransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommissionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test commission is always rounded UP using ceil().
     */
    public function test_commission_rounded_up_to_prevent_losses(): void
    {
        $transfer_service = new TransferService(
            app(\App\Repositories\Contracts\UserRepositoryInterface::class),
            app(\App\Repositories\Contracts\TransactionRepositoryInterface::class)
        );

        // Test cases: amount => expected_commission
        $test_cases = [
            1 => 1,      // 1 * 0.015 = 0.015 => ceil = 1
            10 => 1,     // 10 * 0.015 = 0.15 => ceil = 1
            100 => 2,    // 100 * 0.015 = 1.5 => ceil = 2
            1000 => 15,  // 1000 * 0.015 = 15 => ceil = 15
            1001 => 16,  // 1001 * 0.015 = 15.015 => ceil = 16
            6666 => 100, // 6666 * 0.015 = 99.99 => ceil = 100
            6667 => 101, // 6667 * 0.015 = 100.005 => ceil = 101
        ];

        foreach ($test_cases as $amount => $expected_commission) {
            // Create fresh users for each test
            $sender = User::factory()->create([
                'balance' => 1000000,
                'initial_balance' => 1000000,
            ]);
            $receiver = User::factory()->create([
                'balance' => 0,
                'initial_balance' => 0,
            ]);

            $idempotency_key = 'commission-test-'.$amount.'-'.now()->timestamp;

            $transaction = $transfer_service->transfer(
                sender_id: $sender->id,
                receiver_id: $receiver->id,
                amount: $amount,
                idempotency_key: $idempotency_key
            );

            $this->assertEquals(
                $expected_commission,
                $transaction->commission,
                "Amount {$amount} cents: Expected commission {$expected_commission}, got {$transaction->commission}"
            );

            $this->info("Amount: {$amount} cents => Commission: {$transaction->commission} cents (Expected: {$expected_commission})");
        }
    }

    /**
     * Test that commission prevents micro-cent losses over many transactions.
     */
    public function test_commission_prevents_micro_losses_over_time(): void
    {
        $sender = User::factory()->create(['balance' => 10000000, 'initial_balance' => 10000000]);
        $receiver = User::factory()->create(['balance' => 0, 'initial_balance' => 0]);

        $transfer_service = new TransferService(
            app(\App\Repositories\Contracts\UserRepositoryInterface::class),
            app(\App\Repositories\Contracts\TransactionRepositoryInterface::class)
        );
        $amount = 333; // 333 * 0.015 = 4.995 => ceil = 5

        $total_commission_collected = 0;

        // Make 1000 small transactions
        for ($i = 0; $i < 1000; $i++) {
            $transaction = $transfer_service->transfer(
                sender_id: $sender->id,
                receiver_id: $receiver->id,
                amount: $amount,
                idempotency_key: "micro-loss-test-{$i}"
            );

            $total_commission_collected += $transaction->commission;
        }

        // Verify no micro-losses
        $sender->refresh();
        $receiver->refresh();

        $expected_sender_balance = 10000000 - (($amount + 5) * 1000);
        $expected_receiver_balance = $amount * 1000;

        $this->assertEquals($expected_sender_balance, $sender->balance);
        $this->assertEquals($expected_receiver_balance, $receiver->balance);

        $this->info('Commission over 1000 transactions:');
        $this->info('   - Amount per transaction: {$amount} cents');
        $this->info('   - Commission per transaction: 5 cents (ceil of 4.995)');
        $this->info('   - Total commission collected: {$total_commission_collected} cents');
        $this->info('   - Expected total: 5000 cents');
        $this->info('   - Sender final balance: {$sender->balance} cents');
        $this->info('   - Receiver final balance: {$receiver->balance} cents');
        $this->assertEquals(5000, $total_commission_collected);
    }

    /**
     * Test commission calculation matches exact formula.
     */
    public function test_commission_formula_accuracy(): void
    {
        $test_amounts = [
            100,   // $1.00
            500,   // $5.00
            1000,  // $10.00
            5000,  // $50.00
            10000, // $100.00
            25000, // $250.00
        ];

        foreach ($test_amounts as $amount) {
            $expected_commission = (int) ceil($amount * 0.015);

            $sender = User::factory()->create(['balance' => 1000000, 'initial_balance' => 1000000]);
            $receiver = User::factory()->create(['balance' => 0, 'initial_balance' => 0]);

            $transfer_service = new TransferService(
                app(\App\Repositories\Contracts\UserRepositoryInterface::class),
                app(\App\Repositories\Contracts\TransactionRepositoryInterface::class)
            );
            $transaction = $transfer_service->transfer(
                sender_id: $sender->id,
                receiver_id: $receiver->id,
                amount: $amount,
                idempotency_key: 'formula-test-'.$amount
            );

            $this->assertEquals($expected_commission, $transaction->commission);

            $amount_dollars = $amount / 100;
            $commission_dollars = $transaction->commission / 100;
            $this->info("{$amount_dollars} => Commission: \${$commission_dollars} (1.5% rounded up)");
        }
    }

    /**
     * Helper to output info messages.
     */
    private function info(string $message): void
    {
        fwrite(STDOUT, $message."\n");
    }
}

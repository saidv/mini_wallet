<?php

namespace Database\Seeders;

use App\Models\BalanceSnapshot;
use App\Models\Transaction;
use App\Models\TransactionOutbox;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Number of test users to create.
     */
    private const USER_COUNT = 10;

    /**
     * Number of transactions to generate per user (average).
     */
    private const TRANSACTIONS_PER_USER = 5;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $faker = \Faker\Factory::create();

        $this->command->info('Creating '.self::USER_COUNT.' test users...');

        // Create users
        $users = [];

        // Create a test user with known email if it doesn't exist
        $testUser = User::where('email', 'test@pimono.ae')->first();
        if (! $testUser) {
            $testUser = User::create([
                'name' => 'Test User',
                'email' => 'test@pimono.ae',
                'password' => Hash::make('password'),
                'balance' => 100000,
                'initial_balance' => 100000,
            ]);
            $this->command->info('✅ Created test user: test@pimono.ae');
        } else {
            $this->command->info('ℹ Test user already exists: test@pimono.ae');
        }
        $users[] = $testUser;

        // Create remaining random users
        for ($i = 0; $i < self::USER_COUNT; $i++) {
            $balance = $faker->numberBetween(100000, 1000000); // $1,000 to $10,000

            $user = User::create([
                'name' => $faker->name(),
                'email' => $faker->unique()->safeEmail(),
                'password' => Hash::make('password'),
                'balance' => $balance,
                'initial_balance' => $balance,
            ]);

            $users[] = $user;
        }

        $this->command->info('✅ Created '.self::USER_COUNT.' test users with random balances');

        // Create transactions
        $this->command->info('');
        $this->command->info('Creating transactions between users...');

        $totalTransactions = self::USER_COUNT * self::TRANSACTIONS_PER_USER;
        $transactionCount = 0;
        $completedCount = 0;
        $failedCount = 0;

        DB::transaction(function () use ($users, $faker, $totalTransactions, &$transactionCount, &$completedCount, &$failedCount) {
            for ($i = 0; $i < $totalTransactions; $i++) {
                // Pick random sender and receiver (different users)
                $sender = $users[array_rand($users)];
                do {
                    $receiver = $users[array_rand($users)];
                } while ($sender->id === $receiver->id);

                // Random amount between $1 and $500
                $amount = $faker->numberBetween(100, 50000);

                // Calculate commission (1.5% rounded up)
                $commission = (int) ceil($amount * 0.015);

                // 90% completed, 10% failed for realistic data
                $status = $faker->boolean(90) ? 'completed' : 'failed';

                $uuid = (string) Str::uuid();
                $idempotencyKey = Str::uuid();
                $createdAt = $faker->dateTimeBetween('-30 days', 'now');

                // Create transaction
                $transaction = Transaction::create([
                    'uuid' => $uuid,
                    'sender_id' => $sender->id,
                    'receiver_id' => $receiver->id,
                    'amount' => $amount,
                    'commission' => $commission,
                    'status' => $status,
                    'idempotency_key' => $idempotencyKey,
                    'metadata' => json_encode([
                        'ip' => $faker->ipv4(),
                        'user_agent' => $faker->userAgent(),
                        'description' => $faker->sentence(3),
                    ]),
                    'created_at' => $createdAt,
                    'completed_at' => $status === 'completed' ? $createdAt : null,
                ]);

                if ($status === 'completed') {
                    $completedCount++;

                    // Update user balances (this is just for seeding, normally done by TransferService)
                    $senderNewBalance = $sender->balance - ($amount + $commission);
                    $receiverNewBalance = $receiver->balance + $amount;

                    // Only update if sender has enough balance
                    if ($senderNewBalance >= 0) {
                        $sender->update(['balance' => $senderNewBalance]);
                        $receiver->update(['balance' => $receiverNewBalance]);

                        // Create balance snapshots
                        BalanceSnapshot::create([
                            'user_id' => $sender->id,
                            'balance' => $senderNewBalance,
                            'transaction_uuid' => $uuid,
                            'created_at' => $createdAt,
                        ]);

                        BalanceSnapshot::create([
                            'user_id' => $receiver->id,
                            'balance' => $receiverNewBalance,
                            'transaction_uuid' => $uuid,
                            'created_at' => $createdAt,
                        ]);

                        // Create transaction outbox with varied statuses
                        // 85% delivered, 10% pending, 5% failed
                        $randomValue = $faker->numberBetween(1, 100);
                        $outboxStatus = match (true) {
                            $randomValue <= 85 => 'delivered',
                            $randomValue <= 95 => 'pending',
                            default => 'failed',
                        };

                        $outboxData = [
                            'transaction_uuid' => $uuid,
                            'event_type' => 'money_transferred',
                            'payload' => json_encode([
                                'sender_id' => $sender->id,
                                'receiver_id' => $receiver->id,
                                'amount' => $amount,
                                'commission' => $commission,
                                'sender_new_balance' => $senderNewBalance,
                                'receiver_new_balance' => $receiverNewBalance,
                            ]),
                            'status' => $outboxStatus,
                            'attempts' => $faker->numberBetween(0, 3),
                            'created_at' => $createdAt,
                            'updated_at' => $createdAt,
                        ];
                        if ($outboxData['status'] === 'delivered') {
                            $outboxData['delivered_at'] = $createdAt;
                        } elseif ($outboxData['status'] === 'failed') {
                            $outboxData['last_attempted_at'] = $createdAt;
                            $outboxData['error'] = 'Pusher connection timeout';
                        } elseif ($outboxData['status'] === 'pending' && $outboxData['attempts'] > 0) {
                            $outboxData['last_attempted_at'] = $createdAt;
                        }

                        TransactionOutbox::create($outboxData);

                        // Refresh user balances for next iteration
                        $sender->refresh();
                        $receiver->refresh();
                    }
                } else {
                    $failedCount++;

                    // Create outbox entry for failed transaction
                    TransactionOutbox::create([
                        'transaction_uuid' => $uuid,
                        'event_type' => 'money_transferred',
                        'payload' => json_encode([
                            'sender_id' => $sender->id,
                            'receiver_id' => $receiver->id,
                            'amount' => $amount,
                            'commission' => $commission,
                            'error' => 'Transaction failed - insufficient funds or validation error',
                        ]),
                        'status' => 'failed',
                        'attempts' => $faker->numberBetween(1, 3),
                        'last_attempted_at' => $createdAt,
                        'error' => 'Transaction validation failed',
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ]);
                }

                $transactionCount++;
            }
        });

        $this->command->info('Created '.$transactionCount.' transactions');
        $this->command->info('   - Completed: '.$completedCount);
        $this->command->info('   - Failed: '.$failedCount);

        // Summary statistics
        $this->command->info('');
        $this->command->info('Database Summary:');
        $this->command->info('   - Users: '.User::count());
        $this->command->info('   - Transactions: '.Transaction::count());
        $this->command->info('   - Outbox Entries: '.TransactionOutbox::count());
        $this->command->info('   - Balance Snapshots: '.BalanceSnapshot::count());
    }
}

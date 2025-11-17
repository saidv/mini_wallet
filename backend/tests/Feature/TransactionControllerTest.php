<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TransactionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_stats_endpoint_returns_correct_structure()
    {
        $user = User::factory()->create(['balance' => 100000]);
        $this->actingAs($user, 'sanctum');

        $response = $this->getJson('/api/transactions/stats');
        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'total_sent',
                    'total_received',
                    'total_transactions',
                    'total_commission',
                    'net_balance_change',
                ],
            ]);
    }

    public function test_can_create_transaction_and_commission_is_correct()
    {
        $sender = User::factory()->create(['balance' => 100000]);
        $receiver = User::factory()->create(['balance' => 50000]);
        $this->actingAs($sender, 'sanctum');

        $amount = 10000; // 100.00
        $idempotencyKey = Str::uuid()->toString();
        $response = $this->postJson('/api/transactions', [
            'receiver_email' => $receiver->email,
            'amount' => $amount,
        ], [
            'Idempotency-Key' => $idempotencyKey,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'uuid',
                    'amount',
                    'commission',
                    'total_debited',
                    'sender_balance',
                    'receiver_balance',
                    'created_at',
                ],
            ]);

        $transaction = Transaction::where('sender_id', $sender->id)->first();
        $expectedCommission = (int) round($amount * 0.015);
        $this->assertEquals($expectedCommission, $transaction->commission);
        $this->assertEquals($sender->fresh()->balance, 100000 - $amount - $expectedCommission);
        $this->assertEquals($receiver->fresh()->balance, 50000 + $amount);
    }

    public function test_idempotency_prevents_duplicate_transactions()
    {
        $sender = User::factory()->create(['balance' => 100000]);
        $receiver = User::factory()->create(['balance' => 50000]);
        $this->actingAs($sender, 'sanctum');

        $amount = 10000;
        $idempotencyKey = Str::uuid()->toString();
        $payload = [
            'receiver_email' => $receiver->email,
            'amount' => $amount,
        ];
        $headers = [
            'Idempotency-Key' => $idempotencyKey,
        ];

        $first = $this->postJson('/api/transactions', $payload, $headers);
        $second = $this->postJson('/api/transactions', $payload, $headers);

        $first->assertStatus(201);
        $second->assertStatus(201);
        $this->assertEquals(
            $first->json('uuid'),
            $second->json('uuid'),
            'Idempotency should return the same transaction UUID'
        );
        $this->assertCount(1, Transaction::where('sender_id', $sender->id)->get());
    }

    public function test_cannot_send_money_with_insufficient_balance()
    {
        $sender = User::factory()->create(['balance' => 100]); // Too low
        $receiver = User::factory()->create(['balance' => 50000]);
        $this->actingAs($sender, 'sanctum');

        $amount = 10000;
        $idempotencyKey = Str::uuid()->toString();
        $response = $this->postJson('/api/transactions', [
            'receiver_email' => $receiver->email,
            'amount' => $amount,
        ], [
            'Idempotency-Key' => $idempotencyKey,
        ]);

        $response->assertStatus(400);
        $this->assertCount(0, Transaction::where('sender_id', $sender->id)->get());
    }
}

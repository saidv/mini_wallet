<?php

namespace App\Jobs;

use App\Models\TransactionOutbox;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Pusher\Pusher;

/**
 * Processes outbox entries and broadcasts events to Pusher
 *
 * This job implements the Outbox Pattern for reliable event publishing:
 * 1. Transaction commits create outbox entries
 * 2. This job processes entries asynchronously
 * 3. Broadcasts to Pusher after processing
 * 4. Marks entries as processed or failed
 *
 * Benefits:
 * - Ensures events are never lost (outbox is part of transaction)
 * - Enables retry on failure
 * - Decouples event broadcasting from transaction logic
 * - Improves API response time (async processing)
 */
class ProcessTransactionOutbox implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The number of times the job may be attempted.
     * After 5 failures, the job will be marked as permanently failed.
     *
     * @var int
     */
    public $tries = 5;

    /**
     * The number of seconds to wait before retrying the job after failure.
     * Implements exponential backoff: 10s, 20s, 40s, 80s, 160s
     *
     * @var array
     */
    public $backoff = [10, 20, 40, 80, 160];

    /**
     * The maximum number of seconds the job can run before timing out.
     * Pusher API calls should be fast, but we allow buffer time for network latency.
     *
     * @var int
     */
    public $timeout = 30;

    /**
     * The ID of the outbox entry to process
     */
    protected int $outboxId;

    /**
     * Create a new job instance.
     *
     * @param  int  $outboxId  The ID of the TransactionOutbox record to process
     */
    public function __construct(int $outboxId)
    {
        $this->outboxId = $outboxId;
        $this->onQueue('events'); // High-priority queue for event broadcasting
    }

    /**
     * Execute the job.
     *
     * Process flow:
     * 1. Load outbox entry with pessimistic lock
     * 2. Skip if already processed
     * 3. Validate payload structure
     * 4. Broadcast event to Pusher channels
     * 5. Mark as processed with timestamp
     * 6. Log any failures for debugging
     */
    public function handle(): void
    {
        Log::info('Processing outbox entry', ['outbox_id' => $this->outboxId]);

        try {
            // Load the outbox entry with lock to prevent duplicate processing
            $outbox = TransactionOutbox::lockForUpdate()->find($this->outboxId);

            if (! $outbox) {
                Log::warning('Outbox entry not found', ['outbox_id' => $this->outboxId]);

                return;
            }

            // Skip if already delivered (idempotency check)
            if ($outbox->status === 'delivered') {
                Log::info('Outbox entry already delivered', ['outbox_id' => $this->outboxId]);

                return;
            }

            // Validate payload structure
            $payload = $outbox->payload;
            if (is_string($payload)) {
                $payload = json_decode($payload, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $this->markAsFailed($outbox, 'Invalid JSON payload');
                    return;
                }
            }
            if (! $this->validatePayload($payload)) {
                $this->markAsFailed($outbox, 'Invalid payload structure');

                return;
            }

            // Broadcast to Pusher
            $this->broadcastEvent($outbox->event_type, $payload);

            // Mark as processed
            $outbox->update([
                'status' => 'delivered',
                'delivered_at' => now(),
                'last_attempted_at' => now(),
                'attempts' => $outbox->attempts,
            ]);

            Log::info('Outbox entry processed successfully', [
                'outbox_id' => $this->outboxId,
                'event_type' => $outbox->event_type,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to process outbox entry', [
                'outbox_id' => $this->outboxId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Increment attempts count
            if ($outbox ?? null) {
                $outbox->increment('attempts');
            }

            // Re-throw to trigger Laravel's retry mechanism
            throw $e;
        }
    }

    /**
     * Validate the outbox payload structure
     *
     * @param array $payload Payload array to validate
     * @return bool
     */
    protected function validatePayload(array $payload): bool
    {
        // Required fields for money.transferred event
        $required_fields = [
            'transaction_uuid',
            'sender_id',
            'receiver_id',
            'amount',
            'commission',
            'sender_balance',
            'receiver_balance',
        ];

        foreach ($required_fields as $field) {
            if (! isset($payload[$field])) {
                Log::error('Missing required field in payload', [
                    'outbox_id' => $this->outboxId,
                    'missing_field' => $field,
                ]);

                return false;
            }
        }

        return true;
    }

    /**
     * Broadcast event to Pusher channels
     *
     * Broadcasts to two private channels:
     * 1. private-user.{sender_id} - Notify sender of debit
     * 2. private-user.{receiver_id} - Notify receiver of credit
     *
     * @throws \Exception
     */
    /**
     * Broadcast event to Pusher channels
     *
     * @param string $event_type Event type
     * @param array $payload Event payload
     * @return void
     */
    protected function broadcastEvent(string $event_type, array $payload): void
    {
        // Initialize Pusher
        $pusher = new Pusher(
            config('broadcasting.connections.pusher.key'),
            config('broadcasting.connections.pusher.secret'),
            config('broadcasting.connections.pusher.app_id'),
            [
                'cluster' => config('broadcasting.connections.pusher.options.cluster'),
                'useTLS' => config('broadcasting.connections.pusher.options.useTLS', true),
            ]
        );

        // Load sender for name (needed for receiver notification)
        $sender = \App\Models\User::find($payload['sender_id']);

        if (! $sender) {
            throw new \Exception('Sender not found');
        }

        $amount_formatted = number_format($payload['amount'] / 100, 2);

        // Prepare event data for receiver only (they're the ones who need notification)
        $receiver_data = [
            'transaction_uuid' => $payload['transaction_uuid'],
            'amount' => $payload['amount'],
            'new_balance' => $payload['receiver_balance'],
            'sender' => [
                'id' => $payload['sender_id'],
                'name' => $sender->name,
                'email' => $sender->email,
            ],
            'message' => "You received \${$amount_formatted} from {$sender->name}",
            'timestamp' => now()->toIso8601String(),
        ];

        // Broadcast only to receiver's private channel
        // Sender doesn't need notification (they initiated the action)
        $receiver_channel = "private-user.{$payload['receiver_id']}";
        $pusher->trigger($receiver_channel, 'money.received', $receiver_data);

        Log::info('Broadcasted money received notification', [
            'channel' => $receiver_channel,
            'transaction_uuid' => $payload['transaction_uuid'],
            'amount' => $amount_formatted,
            'sender' => $sender->name,
        ]);
    }

    /**
     * Mark the outbox entry as failed
     *
     * @param TransactionOutbox $outbox Outbox entry instance
     * @param string $reason Failure reason
     * @return void
     */
    protected function markAsFailed(TransactionOutbox $outbox, string $reason): void
    {
        $outbox->update([
            'status' => 'failed',
            'last_attempted_at' => now(),
            'error' => $reason,
        ]);

        Log::error('Outbox entry marked as failed', [
            'outbox_id' => $outbox->id,
            'reason' => $reason,
        ]);
    }

    /**
     * Handle a job failure.
     *
     * Called after all retry attempts have been exhausted.
     * Marks the outbox entry as permanently failed.
     *
     * @param \Throwable $exception Exception that caused the failure
     * @return void
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Job permanently failed after all retries', [
            'outbox_id' => $this->outboxId,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);

        // Mark outbox entry as failed
        $outbox = TransactionOutbox::find($this->outboxId);
        if ($outbox) {
            $outbox->update([
                'status' => 'failed',
                'last_attempted_at' => now(),
                'error' => $exception->getMessage(),
            ]);
        }

        // TODO: Send alert to monitoring system (Sentry, Bugsnag, etc.)
        // TODO: Send notification to ops team for manual intervention
    }
}

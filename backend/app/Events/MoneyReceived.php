<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MoneyReceived implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $transaction_uuid;
    public $amount;
    public $new_balance;
    public $sender_id;
    public $sender_name;
    public $sender_email;
    public $receiver_id;
    public $message;
    public $timestamp;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(array $payload)
    {
        $this->transaction_uuid = $payload['transaction_uuid'];
        $this->amount = $payload['amount'];
        $this->new_balance = $payload['new_balance'];
        $this->sender_id = $payload['sender']['id'];
        $this->sender_name = $payload['sender']['name'];
        $this->sender_email = $payload['sender']['email'];
        $this->receiver_id = $payload['receiver_id'];
        $this->message = $payload['message'];
        $this->timestamp = $payload['timestamp'];
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        // The event should be broadcast to the receiver's private channel
        return new PrivateChannel('user.' . $this->receiver_id);
    }

    /**
     * The event's broadcast name.
     *
     * @return string
     */
    public function broadcastAs()
    {
        return 'money.received';
    }
}

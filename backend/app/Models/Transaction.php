<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Transaction extends Model
{
    use HasUuids;

    /**
     * Indicates if the model should be timestamped.
     * Only use created_at, not updated_at (immutable ledger).
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'uuid';

    /**
     * The "type" of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'sender_id',
        'receiver_id',
        'amount',
        'commission',
        'status',
        'idempotency_key',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'commission' => 'integer',
            'metadata' => 'array',
            'created_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Get the sender user.
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * Get the receiver user.
     */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    /**
     * Get the transaction outbox entry.
     */
    public function outbox(): HasOne
    {
        return $this->hasOne(TransactionOutbox::class, 'transaction_uuid', 'uuid');
    }

    /**
     * Get balance snapshots created by this transaction.
     */
    public function balanceSnapshots(): HasMany
    {
        return $this->hasMany(BalanceSnapshot::class, 'transaction_uuid', 'uuid');
    }

    /**
     * Get amount in dollars (formatted).
     */
    public function getAmountInDollarsAttribute(): float
    {
        return $this->amount / 100;
    }

    /**
     * Get commission in dollars (formatted).
     */
    public function getCommissionInDollarsAttribute(): float
    {
        return $this->commission / 100;
    }

    /**
     * Get total debited amount (amount + commission).
     */
    public function getTotalDebitedAttribute(): int
    {
        return $this->amount + $this->commission;
    }
}

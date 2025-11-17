<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transaction_outbox', function (Blueprint $table) {
            $table->id();

            // Reference to the transaction this event is for
            $table->uuid('transaction_uuid');
            $table->foreign('transaction_uuid')
                ->references('uuid')
                ->on('transactions')
                ->onDelete('cascade');

            // Event type (for future extensibility)
            $table->string('event_type', 100)->default('money_transferred');

            // Event payload (JSON) - contains all data for the event
            $table->json('payload');

            // Processing status
            $table->enum('status', ['pending', 'processing', 'delivered', 'failed'])->default('pending');

            // Retry tracking
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamp('delivered_at')->nullable();

            // Error tracking for failed deliveries
            $table->text('error')->nullable();

            // Timestamps
            $table->timestamps();

            // Indexes for outbox processing
            $table->index(['status', 'created_at'], 'idx_outbox_processing');
            $table->index('transaction_uuid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_outbox');
    }
};

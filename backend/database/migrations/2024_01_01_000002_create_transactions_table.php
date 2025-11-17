<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            // Primary key: UUID for better distribution and security
            $table->uuid('uuid')->primary();

            // Foreign keys to users table
            $table->foreignId('sender_id')->constrained('users')->onDelete('restrict');
            $table->foreignId('receiver_id')->constrained('users')->onDelete('restrict');

            // Money fields (stored as integer cents)
            $table->bigInteger('amount')->comment('Amount transferred in cents');
            $table->bigInteger('commission')->comment('Commission charged in cents (1.5% rounded up)');

            // Transaction status (simplified: completed or failed)
            $table->enum('status', ['completed', 'failed'])->default('completed');

            // Idempotency key for duplicate prevention
            $table->string('idempotency_key', 255)->unique();

            // Metadata (JSON field for additional info: IP, user agent, etc.)
            $table->json('metadata')->nullable();

            // Timestamps
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();

            // Indexes for performance
            $table->index(['sender_id', 'created_at'], 'idx_sender_date');
            $table->index(['receiver_id', 'created_at'], 'idx_receiver_date');
            $table->index(['status', 'created_at'], 'idx_status_date');
            $table->index('created_at');
        });

        // Add comment to table
        DB::statement("ALTER TABLE transactions COMMENT='Immutable append-only transaction ledger'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};

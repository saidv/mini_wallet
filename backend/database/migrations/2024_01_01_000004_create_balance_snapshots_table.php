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
        Schema::create('balance_snapshots', function (Blueprint $table) {
            $table->id();

            // User whose balance this snapshot represents
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');

            // Balance at this point in time (integer cents)
            $table->bigInteger('balance');

            // Transaction that caused this balance change
            $table->uuid('transaction_uuid');
            $table->foreign('transaction_uuid')
                ->references('uuid')
                ->on('transactions')
                ->onDelete('restrict');

            // Timestamp
            $table->timestamp('created_at')->useCurrent();

            // Indexes for audit queries
            $table->index(['user_id', 'created_at'], 'idx_user_snapshots');
            $table->index('transaction_uuid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('balance_snapshots');
    }
};

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
        Schema::create('sync_checkpoints', function (Blueprint $table) {
            $table->id();
            $table->string('sync_type')->index(); // 'open_orders', 'processed_orders', etc.
            $table->string('source')->nullable()->index(); // 'linnworks', 'manual', etc.
            $table->timestamp('last_sync_at')->index();
            $table->timestamp('sync_started_at')->nullable();
            $table->timestamp('sync_completed_at')->nullable();
            $table->enum('status', ['pending', 'in_progress', 'completed', 'failed'])->default('pending')->index();
            $table->integer('records_synced')->default(0);
            $table->integer('records_created')->default(0);
            $table->integer('records_updated')->default(0);
            $table->integer('records_failed')->default(0);
            $table->json('metadata')->nullable(); // Store additional sync info
            $table->text('error_message')->nullable();
            $table->timestamps();

            // Composite indexes for common queries
            $table->index(['sync_type', 'status', 'last_sync_at']);
            $table->index(['sync_type', 'last_sync_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sync_checkpoints');
    }
};

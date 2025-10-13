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
        Schema::create('sync_logs', function (Blueprint $table) {
            $table->id();
            $table->string('sync_type'); // 'open_orders', 'historical_orders', 'order_updates'
            $table->string('status'); // 'started', 'completed', 'failed'
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->integer('total_fetched')->default(0);
            $table->integer('total_created')->default(0);
            $table->integer('total_updated')->default(0);
            $table->integer('total_skipped')->default(0);
            $table->integer('total_failed')->default(0);
            $table->json('metadata')->nullable(); // Store additional sync details
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['sync_type', 'status']);
            $table->index('started_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sync_logs');
    }
};

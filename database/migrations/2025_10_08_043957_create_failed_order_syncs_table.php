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
        Schema::create('failed_order_syncs', function (Blueprint $table) {
            $table->id();
            $table->string('order_id')->nullable()->index();
            $table->string('order_number')->nullable()->index();
            $table->string('order_type')->index(); // 'open' or 'processed'
            $table->string('failure_reason');
            $table->text('error_message')->nullable();
            $table->json('order_data')->nullable();
            $table->json('exception_context')->nullable();
            $table->unsignedInteger('attempt_count')->default(1);
            $table->timestamp('last_attempted_at');
            $table->timestamp('next_retry_at')->nullable()->index();
            $table->boolean('is_resolved')->default(false)->index();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['is_resolved', 'next_retry_at']);
            $table->index(['order_type', 'is_resolved']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('failed_order_syncs');
    }
};

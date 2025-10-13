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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('linnworks_order_id')->unique();
            $table->string('order_number');
            $table->string('channel_name');
            $table->string('channel_reference_number')->nullable();
            $table->string('source')->nullable();
            $table->string('sub_source')->nullable();
            $table->string('external_reference')->nullable();
            $table->decimal('total_value', 10, 2);
            $table->decimal('total_discount', 10, 2)->default(0);
            $table->decimal('postage_cost', 10, 2)->default(0);
            $table->decimal('total_paid', 10, 2);
            $table->decimal('profit_margin', 10, 2)->nullable();
            $table->string('currency', 3);
            $table->enum('status', ['pending', 'processed', 'cancelled', 'refunded'])->default('pending');
            $table->json('addresses')->nullable(); // Store billing/shipping addresses
            $table->timestamp('received_date');
            $table->timestamp('processed_date')->nullable();
            $table->timestamp('dispatched_date')->nullable();
            $table->boolean('is_resend')->default(false);
            $table->boolean('is_exchange')->default(false);
            $table->text('notes')->nullable();
            $table->json('raw_data')->nullable(); // Store full API response
            $table->timestamps();

            $table->index(['received_date', 'channel_name']);
            $table->index(['processed_date', 'status']);
            $table->index('total_value');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};

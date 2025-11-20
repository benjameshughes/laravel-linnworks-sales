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
        Schema::create('order_shipping', function (Blueprint $table) {
            // Primary identifier
            $table->id();

            // Foreign key (1-to-1 with orders)
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();

            // Tracking & carrier information
            $table->string('tracking_number')->nullable();
            $table->string('vendor')->nullable();
            $table->string('postal_service_id')->nullable();
            $table->string('postal_service_name')->nullable();

            // Weight information
            $table->decimal('total_weight', 10, 3)->nullable();
            $table->decimal('item_weight', 10, 3)->nullable();

            // Package information
            $table->string('package_category')->nullable();
            $table->string('package_type')->nullable();

            // Shipping costs
            $table->decimal('postage_cost', 12, 2)->nullable();
            $table->decimal('postage_cost_ex_tax', 12, 2)->nullable();

            // Shipping flags
            $table->boolean('label_printed')->default(false);
            $table->string('label_error')->nullable();
            $table->boolean('invoice_printed')->default(false);
            $table->boolean('pick_list_printed')->default(false);
            $table->boolean('partial_shipped')->default(false);
            $table->boolean('manual_adjust')->default(false);

            // Laravel timestamps
            $table->timestamps();

            // Strategic index
            $table->index('tracking_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_shipping');
    }
};

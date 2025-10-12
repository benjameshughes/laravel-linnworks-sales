<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_shipping', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('tracking_number')->nullable()->index();
            $table->string('vendor')->nullable(); // DX Freight, Royal Mail, etc
            $table->string('postal_service_id')->nullable();
            $table->string('postal_service_name')->nullable(); // DX Secure, etc
            $table->decimal('total_weight', 10, 3)->nullable();
            $table->decimal('item_weight', 10, 3)->nullable();
            $table->string('package_category')->nullable();
            $table->string('package_type')->nullable();
            $table->decimal('postage_cost', 10, 2)->nullable();
            $table->decimal('postage_cost_ex_tax', 10, 2)->nullable();
            $table->boolean('label_printed')->default(false);
            $table->string('label_error')->nullable();
            $table->boolean('invoice_printed')->default(false);
            $table->boolean('pick_list_printed')->default(false);
            $table->boolean('partial_shipped')->default(false);
            $table->boolean('manual_adjust')->default(false);
            $table->timestamps();

            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_shipping');
    }
};

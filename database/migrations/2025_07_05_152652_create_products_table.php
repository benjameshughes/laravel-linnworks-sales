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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('linnworks_id')->unique();
            $table->string('sku')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category_id')->nullable();
            $table->string('category_name')->nullable();
            $table->string('brand')->nullable();
            $table->decimal('purchase_price', 10, 4)->nullable();
            $table->decimal('retail_price', 10, 4)->nullable();
            $table->decimal('weight', 8, 3)->nullable();
            $table->json('dimensions')->nullable();
            $table->string('barcode')->nullable();
            $table->integer('stock_level')->default(0);
            $table->integer('stock_minimum')->default(0);
            $table->integer('stock_in_orders')->default(0);
            $table->integer('stock_due')->default(0);
            $table->integer('stock_available')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_date')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->index(['sku']);
            $table->index(['category_name']);
            $table->index(['is_active']);
            $table->index(['stock_available']);
            $table->index(['last_synced_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};

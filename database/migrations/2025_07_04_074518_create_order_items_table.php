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
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->string('linnworks_item_id');
            $table->string('sku');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category')->nullable();
            $table->integer('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total_price', 10, 2);
            $table->decimal('cost_price', 10, 2)->nullable();
            $table->decimal('profit_margin', 10, 2)->nullable();
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->string('bin_rack')->nullable();
            $table->boolean('is_service')->default(false);
            $table->json('item_attributes')->nullable(); // Store additional item data
            $table->timestamps();
            
            $table->index(['order_id', 'sku']);
            $table->index('sku');
            $table->index('category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};

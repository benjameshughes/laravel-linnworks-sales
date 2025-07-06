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
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('item_id')->nullable();
            $table->string('sku')->index();
            $table->string('item_title');
            $table->integer('quantity');
            $table->decimal('unit_cost', 12, 4)->default(0);
            $table->decimal('price_per_unit', 12, 4);
            $table->decimal('line_total', 12, 4);
            $table->decimal('discount_amount', 12, 4)->default(0);
            $table->decimal('tax_amount', 12, 4)->default(0);
            $table->string('category_name')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            // Add composite index for common queries
            $table->index(['order_id', 'sku']);
            $table->index(['sku', 'created_at']);
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
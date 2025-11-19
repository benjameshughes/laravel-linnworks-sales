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
            // Primary identifier
            $table->id();

            // Foreign key
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            // Linnworks identifiers
            $table->string('item_id')->nullable();
            $table->string('stock_item_id')->nullable()->index();
            $table->unsignedBigInteger('stock_item_int_id')->nullable();
            $table->string('row_id')->nullable();
            $table->string('item_number')->nullable();

            // SKU & Titles
            $table->string('sku')->nullable()->index();
            $table->text('item_title')->nullable();
            $table->string('item_source')->nullable();
            $table->string('channel_sku')->nullable();
            $table->text('channel_title')->nullable();
            $table->string('barcode_number')->nullable();

            // Quantity
            $table->integer('quantity')->default(0);
            $table->integer('part_shipped_qty')->nullable();

            // Category
            $table->string('category_name')->nullable();

            // Pricing
            $table->decimal('price_per_unit', 12, 2)->default(0);
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2)->default(0);
            $table->decimal('cost', 12, 2)->default(0);
            $table->decimal('cost_inc_tax', 12, 2)->default(0);
            $table->decimal('despatch_stock_unit_cost', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('discount_value', 12, 2)->default(0);

            // Tax
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('tax_rate', 8, 4)->default(0);
            $table->decimal('sales_tax', 12, 2)->default(0);
            $table->boolean('tax_cost_inclusive')->default(false);

            // Stock levels
            $table->boolean('stock_levels_specified')->default(false);
            $table->integer('stock_level')->nullable();
            $table->integer('available_stock')->nullable();
            $table->integer('on_order')->nullable();
            $table->integer('stock_level_indicator')->nullable();

            // Inventory tracking
            $table->unsignedTinyInteger('inventory_tracking_type')->nullable();
            $table->boolean('is_batched_stock_item')->default(false);
            $table->boolean('is_warehouse_managed')->default(false);
            $table->boolean('is_unlinked')->default(false);
            $table->boolean('batch_number_scan_required')->default(false);
            $table->boolean('serial_number_scan_required')->default(false);

            // Shipping
            $table->boolean('part_shipped')->default(false);
            $table->decimal('weight', 10, 3)->default(0);
            $table->decimal('shipping_cost', 12, 2)->default(0);
            $table->string('bin_rack')->nullable();
            $table->json('bin_racks')->nullable();

            // Product attributes
            $table->boolean('is_service')->default(false);
            $table->boolean('has_image')->default(false);
            $table->string('image_id')->nullable();
            $table->unsignedInteger('market')->nullable();

            // Composite items & additional data
            $table->json('composite_sub_items')->nullable();
            $table->json('additional_info')->nullable();

            // Metadata
            $table->timestamp('added_at')->nullable();

            // Laravel timestamps
            $table->timestamps();

            // Strategic indexes
            $table->index(['order_id', 'sku']);
            $table->index(['stock_item_id', 'order_id']);
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

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
        Schema::table('order_items', function (Blueprint $table) {
            // Identification fields
            $table->string('item_number')->nullable()->after('item_id');
            $table->string('item_source')->nullable()->after('item_number');
            $table->string('row_id')->nullable()->after('linnworks_item_id');
            $table->integer('stock_item_int_id')->nullable()->after('row_id');

            // Channel-specific fields
            $table->string('channel_sku')->nullable()->after('sku');
            $table->text('channel_title')->nullable()->after('title');

            // Pricing & Costs (in addition to existing fields)
            $table->decimal('item_tax', 10, 2)->nullable()->after('tax_rate');
            $table->decimal('cost', 10, 2)->nullable()->after('cost_price');
            $table->decimal('cost_inc_tax', 10, 2)->nullable()->after('cost');
            $table->decimal('despatch_stock_unit_cost', 10, 2)->nullable()->after('cost_inc_tax');
            $table->decimal('discount', 10, 2)->nullable()->after('discount_amount');
            $table->decimal('sales_tax', 10, 2)->nullable()->after('item_tax');
            $table->boolean('tax_cost_inclusive')->default(false)->after('sales_tax');
            $table->decimal('shipping_cost', 10, 2)->nullable()->after('total_price');

            // Stock & Inventory
            $table->boolean('stock_levels_specified')->default(false)->after('is_service');
            $table->integer('stock_level')->nullable()->after('stock_levels_specified');
            $table->integer('available_stock')->nullable()->after('stock_level');
            $table->integer('on_order')->nullable()->after('available_stock');
            $table->integer('stock_level_indicator')->nullable()->after('on_order');
            $table->integer('inventory_tracking_type')->nullable()->after('stock_level_indicator');
            $table->boolean('is_batched_stock_item')->default(false)->after('inventory_tracking_type');
            $table->boolean('is_warehouse_managed')->default(false)->after('is_batched_stock_item');
            $table->boolean('is_unlinked')->default(false)->after('is_warehouse_managed');

            // Shipping & Physical attributes
            $table->boolean('part_shipped')->default(false)->after('is_service');
            $table->integer('part_shipped_qty')->nullable()->after('part_shipped');
            $table->decimal('weight', 10, 2)->nullable()->after('part_shipped_qty');
            $table->json('bin_racks')->nullable()->after('bin_rack');

            // Batch/Serial tracking
            $table->boolean('batch_number_scan_required')->default(false)->after('is_batched_stock_item');
            $table->boolean('serial_number_scan_required')->default(false)->after('batch_number_scan_required');

            // Barcodes & Product info
            $table->string('barcode_number')->nullable()->after('sku');

            // Images
            $table->boolean('has_image')->default(false)->after('item_attributes');
            $table->string('image_id')->nullable()->after('has_image');

            // Channel integration
            $table->integer('market')->nullable()->after('channel_title');

            // Composite items & Additional data
            $table->json('composite_sub_items')->nullable()->after('item_attributes');
            $table->json('additional_info')->nullable()->after('composite_sub_items');

            // Metadata
            $table->timestamp('added_date')->nullable()->after('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn([
                'item_number',
                'item_source',
                'row_id',
                'stock_item_int_id',
                'channel_sku',
                'channel_title',
                'item_tax',
                'cost',
                'cost_inc_tax',
                'despatch_stock_unit_cost',
                'discount',
                'sales_tax',
                'tax_cost_inclusive',
                'shipping_cost',
                'stock_levels_specified',
                'stock_level',
                'available_stock',
                'on_order',
                'stock_level_indicator',
                'inventory_tracking_type',
                'is_batched_stock_item',
                'is_warehouse_managed',
                'is_unlinked',
                'part_shipped',
                'part_shipped_qty',
                'weight',
                'bin_racks',
                'batch_number_scan_required',
                'serial_number_scan_required',
                'barcode_number',
                'has_image',
                'image_id',
                'market',
                'composite_sub_items',
                'additional_info',
                'added_date',
            ]);
        });
    }
};

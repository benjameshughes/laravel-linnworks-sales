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
        Schema::table('orders', function (Blueprint $table) {
            // Single column indexes for frequent lookups
            $table->index('order_number', 'idx_orders_order_number');
            $table->index('channel_name', 'idx_orders_channel_name');
            $table->index('status', 'idx_orders_status');
            $table->index('created_at', 'idx_orders_created_at');
            $table->index('updated_at', 'idx_orders_updated_at');

            // Composite indexes for common query patterns
            // Dashboard: date range + channel filtering
            $table->index(['received_date', 'channel_name', 'status'], 'idx_orders_date_channel_status');

            // Analytics: channel performance over time
            $table->index(['channel_name', 'received_date', 'total_charge'], 'idx_orders_channel_analytics');

            // Sync operations: open/processed filtering
            $table->index(['status', 'received_date'], 'idx_orders_status_date');

            // Order state queries
            $table->index(['is_resend', 'is_exchange', 'status'], 'idx_orders_flags_status');

            // Date range queries with totals
            $table->index(['received_date', 'total_charge'], 'idx_orders_date_revenue');
            $table->index(['processed_date', 'total_charge'], 'idx_orders_processed_revenue');

            // Soft deletes optimization
            $table->index('deleted_at', 'idx_orders_deleted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Drop indexes in reverse order
            $table->dropIndex('idx_orders_deleted_at');
            $table->dropIndex('idx_orders_processed_revenue');
            $table->dropIndex('idx_orders_date_revenue');
            $table->dropIndex('idx_orders_flags_status');
            $table->dropIndex('idx_orders_status_date');
            $table->dropIndex('idx_orders_channel_analytics');
            $table->dropIndex('idx_orders_date_channel_status');
            $table->dropIndex('idx_orders_updated_at');
            $table->dropIndex('idx_orders_created_at');
            $table->dropIndex('idx_orders_status');
            $table->dropIndex('idx_orders_channel_name');
            $table->dropIndex('idx_orders_order_number');
        });
    }
};

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
            // Add missing columns for Linnworks integration
            $table->string('order_source')->nullable()->after('sub_source');
            $table->string('subsource')->nullable()->after('order_source');
            $table->decimal('tax', 10, 2)->default(0)->after('postage_cost');
            $table->integer('order_status')->default(0)->after('status');
            $table->string('location_id')->nullable()->after('order_status');
            $table->json('items')->nullable()->after('raw_data');
            
            // Update indexes
            $table->index('order_source');
            $table->index('order_status');
        });
        
        // Rename columns separately to avoid conflicts
        Schema::table('orders', function (Blueprint $table) {
            $table->renameColumn('total_value', 'total_charge');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'order_source', 
                'subsource', 
                'tax', 
                'order_status', 
                'location_id', 
                'items'
            ]);
            $table->dropIndex(['order_source']);
            $table->dropIndex(['order_status']);
        });
        
        Schema::table('orders', function (Blueprint $table) {
            $table->renameColumn('total_charge', 'total_value');
        });
    }
};

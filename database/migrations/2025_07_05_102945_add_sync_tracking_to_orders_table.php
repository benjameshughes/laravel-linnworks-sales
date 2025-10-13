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
            // Add sync tracking fields
            $table->timestamp('last_synced_at')->nullable()->after('updated_at');
            $table->boolean('is_open')->default(true)->after('status')->index();
            $table->boolean('has_refund')->default(false)->after('is_open');
            $table->string('sync_status')->default('synced')->after('has_refund');
            $table->json('sync_metadata')->nullable()->after('sync_status');

            // Add soft deletes for data integrity
            $table->softDeletes();

            // Add composite indexes for better query performance
            $table->index(['is_open', 'last_synced_at']);
            $table->index(['status', 'has_refund']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'last_synced_at',
                'is_open',
                'has_refund',
                'sync_status',
                'sync_metadata',
            ]);
            $table->dropSoftDeletes();

            // Drop indexes
            $table->dropIndex(['is_open', 'last_synced_at']);
            $table->dropIndex(['status', 'has_refund']);
        });
    }
};

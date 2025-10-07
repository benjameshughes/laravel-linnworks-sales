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
            // Status tracking fields
            $table->boolean('is_cancelled')->default(false)->after('is_processed');
            $table->string('status_reason')->nullable()->after('is_cancelled');
            $table->timestamp('cancelled_at')->nullable()->after('status_reason');
            $table->timestamp('dispatched_at')->nullable()->after('cancelled_at');

            // Index for common queries
            $table->index(['is_cancelled', 'is_processed']);
            $table->index('cancelled_at');
            $table->index('dispatched_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['is_cancelled', 'is_processed']);
            $table->dropIndex(['cancelled_at']);
            $table->dropIndex(['dispatched_at']);

            $table->dropColumn([
                'is_cancelled',
                'status_reason',
                'cancelled_at',
                'dispatched_at',
            ]);
        });
    }
};

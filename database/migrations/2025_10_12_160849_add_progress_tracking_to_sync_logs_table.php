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
        Schema::table('sync_logs', function (Blueprint $table) {
            $table->string('current_phase')->nullable()->after('status');
            $table->integer('current_step')->default(0)->after('current_phase');
            $table->integer('total_steps')->default(0)->after('current_step');
            $table->text('progress_data')->nullable()->after('total_steps');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sync_logs', function (Blueprint $table) {
            $table->dropColumn(['current_phase', 'current_step', 'total_steps', 'progress_data']);
        });
    }
};

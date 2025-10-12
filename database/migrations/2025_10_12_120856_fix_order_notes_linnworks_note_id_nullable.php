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
        Schema::table('order_notes', function (Blueprint $table) {
            // Drop the unique constraint
            $table->dropUnique(['linnworks_note_id']);
            // Make linnworks_note_id nullable
            $table->string('linnworks_note_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_notes', function (Blueprint $table) {
            $table->string('linnworks_note_id')->nullable(false)->change();
            $table->unique('linnworks_note_id');
        });
    }
};

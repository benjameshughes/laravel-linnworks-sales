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
        Schema::table('linnworks_connections', function (Blueprint $table) {
            // Change to TEXT to accommodate encrypted data (base64 JSON ~250-400 chars)
            $table->text('application_id')->change();
            $table->text('application_secret')->change();
            $table->text('access_token')->change();
            $table->text('session_token')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('linnworks_connections', function (Blueprint $table) {
            // Revert back to varchar(255)
            $table->string('application_id')->change();
            $table->string('application_secret')->change();
            $table->string('access_token')->change();
            $table->string('session_token')->nullable()->change();
        });
    }
};

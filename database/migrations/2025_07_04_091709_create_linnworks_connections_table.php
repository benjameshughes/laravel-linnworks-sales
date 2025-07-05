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
        Schema::create('linnworks_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('application_id');
            $table->string('application_secret');
            $table->string('access_token');
            $table->string('session_token')->nullable();
            $table->string('server_location')->nullable();
            $table->timestamp('session_expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('status')->default('pending');
            $table->json('application_data')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('linnworks_connections');
    }
};

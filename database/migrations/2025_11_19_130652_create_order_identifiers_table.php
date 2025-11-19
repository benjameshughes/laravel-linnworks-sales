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
        Schema::create('order_identifiers', function (Blueprint $table) {
            // Primary identifier
            $table->id();

            // Foreign key (1-to-many with orders)
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            // Identifier/tag information
            $table->string('identifier_key')->index();
            $table->string('identifier_value')->nullable();

            // Laravel timestamps
            $table->timestamps();

            // Strategic index
            $table->index(['order_id', 'identifier_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_identifiers');
    }
};

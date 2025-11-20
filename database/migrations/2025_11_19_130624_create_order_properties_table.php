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
        Schema::create('order_properties', function (Blueprint $table) {
            // Primary identifier
            $table->id();

            // Foreign key (1-to-many with orders)
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            // Property information
            $table->string('property_key')->index();
            $table->text('property_value')->nullable();
            $table->string('property_type')->nullable();

            // Laravel timestamps
            $table->timestamps();

            // Strategic index
            $table->index(['order_id', 'property_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_properties');
    }
};

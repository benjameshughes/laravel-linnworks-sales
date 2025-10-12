<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_properties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('property_type')->index(); // Order, SalesRecordNum, VTN, CXL_REFERENCE, etc
            $table->string('property_name')->index(); // PURCHASE_MARKETPLACE_ID, etc
            $table->text('property_value');
            $table->timestamps();

            $table->index('order_id');
            $table->index(['order_id', 'property_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_properties');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('linnworks_note_id')->unique(); // UUID from Linnworks
            $table->timestamp('note_date')->nullable();
            $table->boolean('is_internal')->default(false);
            $table->text('note_text');
            $table->string('created_by')->nullable(); // System/user name, NOT customer
            $table->timestamps();

            $table->index('order_id');
            $table->index('note_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_notes');
    }
};

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
        Schema::create('order_notes', function (Blueprint $table) {
            // Primary identifier
            $table->id();

            // Foreign key (1-to-many with orders)
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            // Note information
            $table->text('note')->nullable();
            $table->string('note_type')->nullable();
            $table->boolean('is_internal')->default(false);
            $table->timestamp('note_date')->nullable();
            $table->string('noted_by')->nullable();

            // Laravel timestamps
            $table->timestamps();

            // Strategic index
            $table->index(['order_id', 'note_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_notes');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_identifiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->integer('identifier_id'); // From Linnworks API
            $table->string('tag')->index(); // PICKWAVECOMPLETE, etc
            $table->string('name')->nullable(); // Pickwave Complete
            $table->boolean('is_custom')->default(false);
            $table->timestamps();

            $table->index('order_id');
            $table->index(['order_id', 'tag']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_identifiers');
    }
};

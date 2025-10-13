<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('is_parked')->default(false)->after('is_cancelled')->index();
            $table->integer('marker')->default(0)->after('is_parked')->index(); // 0-5 color markers
            $table->timestamp('despatch_by_date')->nullable()->after('dispatched_at')->index();
            $table->integer('num_items')->nullable()->after('despatch_by_date'); // Quick item count
            $table->string('payment_method')->nullable()->after('paid_date');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['marker', 'is_parked', 'despatch_by_date', 'num_items', 'payment_method']);
        });
    }
};

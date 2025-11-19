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
        Schema::create('orders', function (Blueprint $table) {
            // Primary identifier
            $table->id();

            // Linnworks identifiers
            $table->string('linnworks_id')->unique()->nullable()->index();
            $table->unsignedBigInteger('number')->nullable()->index();

            // Order dates
            $table->timestamp('received_at')->nullable()->index();
            $table->timestamp('processed_at')->nullable()->index();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('despatch_by_at')->nullable();

            // Channel information
            $table->string('source')->nullable()->index();
            $table->string('subsource')->nullable();

            // Financial information
            $table->string('currency', 3)->default('GBP');
            $table->decimal('total_charge', 12, 2)->default(0);
            $table->decimal('postage_cost', 12, 2)->default(0);
            $table->decimal('postage_cost_ex_tax', 12, 2)->default(0);
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('profit_margin', 12, 2)->default(0);
            $table->decimal('total_discount', 12, 2)->default(0);
            $table->decimal('country_tax_rate', 8, 4)->nullable();
            $table->decimal('conversion_rate', 12, 6)->default(1);

            // Order status
            $table->unsignedTinyInteger('status')->default(0)->index();
            $table->boolean('is_paid')->default(false)->index();
            $table->boolean('is_cancelled')->default(false)->index();

            // Location
            $table->string('location_id')->nullable();

            // Payment information
            $table->string('payment_method')->nullable();
            $table->string('payment_method_id')->nullable();

            // Reference numbers
            $table->string('channel_reference_number')->nullable();
            $table->string('secondary_reference')->nullable();
            $table->string('external_reference_num')->nullable();

            // Order flags
            $table->unsignedTinyInteger('marker')->default(0)->index();
            $table->boolean('is_parked')->default(false);
            $table->boolean('label_printed')->default(false);
            $table->string('label_error')->nullable();
            $table->boolean('invoice_printed')->default(false);
            $table->boolean('pick_list_printed')->default(false);
            $table->boolean('is_rule_run')->default(false);
            $table->boolean('part_shipped')->default(false);
            $table->boolean('has_scheduled_delivery')->default(false);
            $table->json('pickwave_ids')->nullable();
            $table->unsignedInteger('num_items')->nullable();

            // Laravel timestamps
            $table->timestamps();

            // Strategic indexes
            $table->index(['source', 'received_at']);
            $table->index(['status', 'received_at']);
            $table->index(['is_paid', 'received_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};

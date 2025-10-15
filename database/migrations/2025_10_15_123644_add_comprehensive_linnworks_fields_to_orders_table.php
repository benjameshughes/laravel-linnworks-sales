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
        Schema::table('orders', function (Blueprint $table) {
            // GeneralInfo fields - Document/Printing status
            $table->boolean('label_printed')->default(false)->after('is_parked');
            $table->string('label_error')->nullable()->after('label_printed');
            $table->boolean('invoice_printed')->default(false)->after('label_error');
            $table->boolean('pick_list_printed')->default(false)->after('invoice_printed');
            $table->boolean('is_rule_run')->default(false)->after('pick_list_printed');

            // Shipping status
            $table->boolean('part_shipped')->default(false)->after('is_rule_run');

            // References
            $table->string('secondary_reference')->nullable()->after('channel_reference_number');

            // Delivery scheduling
            $table->boolean('has_scheduled_delivery')->default(false)->after('despatch_by_date');

            // Pickwave tracking
            $table->json('pickwave_ids')->nullable()->after('has_scheduled_delivery');

            // TotalsInfo fields - Tax and currency details
            $table->decimal('postage_cost_ex_tax', 10, 2)->nullable()->after('postage_cost');
            $table->decimal('country_tax_rate', 5, 2)->nullable()->after('tax');
            $table->decimal('conversion_rate', 10, 4)->default(1)->after('currency');

            // Payment method ID
            $table->string('payment_method_id')->nullable()->after('payment_method');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'label_printed',
                'label_error',
                'invoice_printed',
                'pick_list_printed',
                'is_rule_run',
                'part_shipped',
                'secondary_reference',
                'has_scheduled_delivery',
                'pickwave_ids',
                'postage_cost_ex_tax',
                'country_tax_rate',
                'conversion_rate',
                'payment_method_id',
            ]);
        });
    }
};

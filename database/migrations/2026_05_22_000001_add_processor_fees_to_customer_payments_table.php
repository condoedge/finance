<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddProcessorFeesToCustomerPaymentsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        $scale = config('kompo-finance.payment-related-decimal-scale');

        Schema::table('fin_customer_payments', function (Blueprint $table) use ($scale) {
            // Nullable: null = fee not yet reconciled (e.g. Moneris awaiting settlement import).
            $table->decimal('processor_fees', 19, $scale)->nullable()->after('amount');
            $table->decimal('net', 19, $scale)->nullable()->after('processor_fees');
        });

        // Pre-existing manual payments (no payment trace) have no processor fee — mark them reconciled.
        DB::table('fin_customer_payments')
            ->whereNull('payment_trace_id')
            ->update(['processor_fees' => 0]);

        // Seed net; the integrity system recomputes on next save via calculate_payment_net().
        DB::table('fin_customer_payments')->update(['net' => DB::raw('amount - COALESCE(processor_fees, 0)')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('fin_customer_payments', function (Blueprint $table) {
            $table->dropColumn(['processor_fees', 'net']);
        });
    }
}

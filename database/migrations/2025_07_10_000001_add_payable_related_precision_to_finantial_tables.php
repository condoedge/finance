<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     *
     * Adds default handler functionality to account segments to enable
     * automatic value generation based on context (team, fiscal year, etc.)
     */
    public function up(): void
    {
        Schema::table('fin_invoices', function (Blueprint $table) {
            $table->decimal('invoice_due_amount', 19, config('kompo-finance.payment-related-decimal-scale'))->nullable()
                ->change();

            $table->decimal('invoice_total_amount', 19, config('kompo-finance.payment-related-decimal-scale'))->storedAs('invoice_tax_amount + invoice_amount_before_taxes')
                ->change();
        });

        Schema::table('fin_invoice_applies', function (Blueprint $table) {
            $table->decimal('payment_applied_amount', 19, config('kompo-finance.payment-related-decimal-scale'))
                ->change();
        });

        Schema::table('fin_customer_payments', function (Blueprint $table) {
            $table->decimal('amount_left', 19, config('kompo-finance.payment-related-decimal-scale'))->nullable()
                ->change();

            $table->decimal('amount', 19, config('kompo-finance.payment-related-decimal-scale'))
                ->change();
        });

        Schema::table('fin_payment_installment_periods', function (Blueprint $table) {
            $table->decimal('amount', 19, config('kompo-finance.payment-related-decimal-scale'))
                ->change();

            $table->decimal('due_amount', 19, config('kompo-finance.payment-related-decimal-scale'))
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
    }
};

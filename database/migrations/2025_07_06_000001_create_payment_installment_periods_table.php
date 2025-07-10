<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePaymentInstallmentPeriodsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('fin_payment_installment_periods', function (Blueprint $table) {
            addMetaData($table);

            $table->integer('installment_number')->default(1);

            $table->foreignId('invoice_id')
                ->constrained('fin_invoices');

            $table->decimal('due_amount', 19, config('kompo-finance.payment-related-decimal-scale'));
            $table->decimal('amount', 19, config('kompo-finance.payment-related-decimal-scale'));

            $table->date('due_date')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('fin_invoice_details');
    }
}

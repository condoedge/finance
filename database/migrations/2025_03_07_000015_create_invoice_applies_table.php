<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInvoiceAppliesTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('fin_invoice_applies', function (Blueprint $table) {
            addMetaData($table);

            $table->date('apply_date');
            $table->foreignId('invoice_id')->constrained('fin_invoices');
            // $table->decimal('invoice_applied_amount', 19, config('kompo-finance.decimal-scale'));

            // We set the morphable_type to use integers to improve performance
            $table->unsignedBigInteger('applicable_id');
            $table->unsignedSmallInteger('applicable_type');

            $table->index(['applicable_id', 'applicable_type'], 'fin_invoice_applicable_index');

            // $table->foreignId('payment_id')->constrained('fin_customer_payments');
            $table->decimal('payment_applied_amount', 19, config('kompo-finance.payment-related-decimal-scale'));
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('fin_invoice_applies');
    }
}

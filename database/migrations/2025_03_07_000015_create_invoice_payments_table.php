<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInvoicePaymentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('fin_invoice_payments', function (Blueprint $table) {
            addMetaData($table);

            $table->date('apply_date');
            $table->foreignId('invoice_id')->constrained('fin_invoices');
            $table->decimal('invoice_applied_amount', 19, 5);
            $table->foreignId('payment_id')->constrained('fin_customer_payments');
            $table->decimal('payment_applied_amount', 19, 5);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('fin_invoice_payments');
    }
}

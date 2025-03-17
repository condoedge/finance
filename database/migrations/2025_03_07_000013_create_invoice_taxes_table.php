<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInvoiceTaxesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('fin_invoice_taxes', function (Blueprint $table) {
            addMetaData($table);
            
            $table->foreignId('invoice_id')->constrained('fin_invoices');
            $table->foreignId('tax_id')->constrained('fin_taxes');
            $table->foreignId('account_id')->constrained('fin_accounts');
            $table->decimal('tax_amount', 19, 5);
            $table->decimal('tax_rate', 19, 5);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('fin_invoice_taxes');
    }
}

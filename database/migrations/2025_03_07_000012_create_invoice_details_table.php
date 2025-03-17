<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInvoiceDetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('fin_invoice_details', function (Blueprint $table) {
            addMetaData($table);

            $table->foreignId('invoice_id')->constrained('fin_invoices');
            $table->foreignId('revenue_account_id')->constrained('fin_accounts');
            $table->foreignId('product_id')->constrained('fin_products');
            $table->integer('quantity');
            $table->string('description');
            $table->decimal('unit_price', 19, 5);
            $table->decimal('extended_price', 19, 5)->storedAs('quantity * unit_price');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('fin_invoice_details');
    }
}

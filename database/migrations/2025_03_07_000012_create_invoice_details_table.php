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
            $table->foreignId('product_id')->nullable()->constrained('fin_products');
            $table->unsignedInteger('quantity');
            $table->string('name');
            $table->string('description');
            $table->decimal('unit_price', 19, config('kompo-finance.decimal-scale'));
            $table->decimal('extended_price', 19, config('kompo-finance.decimal-scale'))->storedAs('quantity * unit_price');

            $table->decimal('tax_amount', 19, config('kompo-finance.decimal-scale'))->nullable();

            $table->decimal('total_amount', 19, config('kompo-finance.decimal-scale'))->nullable()->storedAs('extended_price + tax_amount');
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

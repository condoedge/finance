<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInvoiceDetailTaxesTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('fin_invoice_detail_taxes', function (Blueprint $table) {
            addMetaData($table);

            $table->foreignId('invoice_detail_id')->constrained('fin_invoice_details');
            $table->foreignId('tax_id')->constrained('fin_taxes');
            $table->foreignId('account_id')->nullable()->constrained('fin_gl_accounts');
            $table->decimal('tax_amount', 19, config('kompo-finance.decimal-scale'));
            $table->decimal('tax_rate', 19, config('kompo-finance.decimal-scale'));
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('fin_invoice_detail_taxes');
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBillDetailTaxesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('fin_bill_detail_taxes', function (Blueprint $table) {
            addMetaData($table);

            $table->foreignId('bill_detail_id')->constrained('fin_bill_details');
            $table->foreignId('account_id')->constrained('fin_accounts');
            $table->foreignId('tax_id')->constrained('fin_taxes');
            
            $table->decimal('tax_rate', 10, 6); // Store rate as decimal (e.g., 0.15 for 15%)
            $table->decimal('tax_amount', 19, config('kompo-finance.decimal-scale'))->default(0);
            
            $table->index(['bill_detail_id']);
            $table->index(['tax_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('fin_bill_detail_taxes');
    }
}

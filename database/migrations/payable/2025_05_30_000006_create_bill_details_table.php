<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBillDetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('fin_bill_details', function (Blueprint $table) {
            addMetaData($table);

            $table->foreignId('bill_id')->constrained('fin_bills');
            $table->foreignId('expense_account_id')->constrained('fin_accounts');
            $table->foreignId('product_id')->nullable()->constrained('fin_products');
            
            $table->integer('quantity');
            $table->string('name');
            $table->text('description')->nullable();
            
            // Amounts - calculated by functions
            $table->decimal('unit_price', 19, config('kompo-finance.decimal-scale'));
            $table->decimal('extended_price', 19, config('kompo-finance.decimal-scale'))->storedAs('quantity * unit_price');
            $table->decimal('tax_amount', 19, config('kompo-finance.decimal-scale'))->default(0);
            $table->decimal('total_amount', 19, config('kompo-finance.decimal-scale'))->storedAs('extended_price + tax_amount');
            
            $table->index(['bill_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('fin_bill_details');
    }
}

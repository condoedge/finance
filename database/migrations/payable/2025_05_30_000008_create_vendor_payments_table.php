<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVendorPaymentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('fin_vendor_payments', function (Blueprint $table) {
            addMetaData($table);

            $table->foreignId('vendor_id')->constrained('fin_vendors');
            $table->date('payment_date');
            $table->decimal('amount', 19, config('kompo-finance.decimal-scale'));
            $table->decimal('amount_left', 19, config('kompo-finance.decimal-scale'))->default(0); // Calculated field
            
            $table->index(['vendor_id']);
            $table->index(['payment_date']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('fin_vendor_payments');
    }
}

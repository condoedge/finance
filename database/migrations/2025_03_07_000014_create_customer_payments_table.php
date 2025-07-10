<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCustomerPaymentsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('fin_customer_payments', function (Blueprint $table) {
            addMetaData($table);

            $table->foreignId('customer_id')->constrained('fin_customers');
            $table->date('payment_date');
            $table->decimal('amount', 19, config('kompo-finance.payment-related-decimal-scale'));
            $table->decimal('amount_left', 19, config('kompo-finance.payment-related-decimal-scale'))->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('fin_customer_payments');
    }
}

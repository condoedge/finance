<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePaymentTracesTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('fin_payment_traces', function (Blueprint $table) {
            addMetaData($table);

            $table->morphs('payable'); // This will create payable_id and payable_type columns

            $table->tinyInteger('status')->default(1);

            $table->string('external_transaction_ref')->nullable();
            $table->string('payment_provider_code')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('payment_traces');
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEntriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('fin_entries', function (Blueprint $table) {
            addMetaData($table);
            
            $table->foreignId('transaction_id')->constrained('fin_transactions');
            $table->foreignId('account_id')->constrained('fin_accounts');
            $table->decimal('debit_amount', 19, config('kompo-finance.decimal-scale'));
            $table->decimal('credit_amount', 19, config('kompo-finance.decimal-scale'));
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('fin_entries');
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRgcqsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::dropIfExists('conciliations');
        Schema::dropIfExists('entries');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('charge_details');
        Schema::dropIfExists('bills');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('sub_accounts');
        Schema::dropIfExists('budgets');
        Schema::dropIfExists('account_balances');
        Schema::dropIfExists('gl_accounts');
        Schema::dropIfExists('funds');
        Schema::dropIfExists('recurrences');
        Schema::dropIfExists('banks');
        Schema::dropIfExists('taxable_tax');
        Schema::dropIfExists('taxes');
        Schema::dropIfExists('rgcqs');

        Schema::create('rgcqs', function (Blueprint $table) {
            
            addMetaData($table);

            $table->integer('level');
            $table->integer('group');
            $table->string('code');
            $table->integer('fund_type_id')->nullable();
            $table->string('type');
            $table->string('name')->nullable();
            $table->string('subname')->nullable();
            $table->text('description')->nullable();
            $table->tinyInteger('enabled')->default(1);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('rgcqs');
    }
}

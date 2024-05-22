<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBudgetDetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('budget_details', function (Blueprint $table) {
            
            addMetaData($table);

            $table->foreignId('budget_id')->constrained();
            $table->foreignId('gl_account_id')->constrained();
            $table->foreignId('fund_id')->constrained();
            $table->decimal('amount', 18, 2);
            $table->date('reference_date')->nullable(); 
            $table->dateTime('included_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('budget_details');
    }
}

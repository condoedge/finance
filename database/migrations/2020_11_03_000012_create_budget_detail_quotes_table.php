<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBudgetDetailQuotesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('budget_detail_quotes', function (Blueprint $table) {
            
            addMetaData($table);

            $table->foreignId('budget_detail_id')->constrained();
            $table->foreignId('customer_id')->constrained();
            $table->decimal('fractions', 15, 6);
            $table->decimal('calc_pct', 10, 6)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('budget_detail_quotes');
    }
}

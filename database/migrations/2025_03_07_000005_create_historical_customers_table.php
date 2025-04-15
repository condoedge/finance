<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateHistoricalCustomersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('fin_historical_customers', function (Blueprint $table) {
            addMetaData($table);

            $table->string('name');
            $table->foreignId('team_id')->constrained('teams');
            $table->foreignId('customer_id')->constrained('fin_customers');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('fin_historical_customers');
    }
}

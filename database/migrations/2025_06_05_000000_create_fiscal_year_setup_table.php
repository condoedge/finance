<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFiscalYearSetupTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('fin_fiscal_year_setup', function (Blueprint $table) {
            addMetaData($table);

            $table->date('fiscal_start_date');
            $table->foreignId('team_id')->constrained('teams');

            $table->unique(['team_id', 'fiscal_start_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('fin_fiscal_year_setup');
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ChangeValideToTaxesTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('fin_taxes', function (Blueprint $table) {
            $table->date('valide_to')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('fin_taxes', function (Blueprint $table) {
            $table->date('valide_to')->nullable(false)->change();
        });
    }
}

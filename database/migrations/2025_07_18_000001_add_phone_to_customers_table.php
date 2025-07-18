<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPhoneToCustomersTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('fin_historical_customers', function (Blueprint $table) {
            $table->string('phone')->nullable();
        });

        Schema::table('fin_customers', function (Blueprint $table) {
            $table->string('phone')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('fin_historical_customers', function (Blueprint $table) {
            $table->dropColumn('phone');
        });

        Schema::table('fin_customers', function (Blueprint $table) {
            $table->dropColumn('phone');
        });
    }
}

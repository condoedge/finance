<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddEmailToCustomersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('fin_historical_customers', function (Blueprint $table) {
            $table->string('email')->nullable();
        });

        Schema::table('fin_customers', function (Blueprint $table) {
            $table->string('email')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('fin_historical_customers', function (Blueprint $table) {
            $table->dropColumn('email');
        });

        Schema::table('fin_customers', function (Blueprint $table) {
            $table->dropColumn('email');
        });
    }
}

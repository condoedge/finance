<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class UpdateCustomerFunctions extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        $sql = file_get_contents(__DIR__ . '/../sql/triggers/insert_historical_customer/insert_historical_customer_v0002.sql');
        DB::unprepared("DROP TRIGGER IF EXISTS trg_insert_historical_customer");
        DB::unprepared(processDelimiters($sql));

    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {

    }
}

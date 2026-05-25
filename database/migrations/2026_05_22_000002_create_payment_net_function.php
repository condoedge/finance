<?php

use Illuminate\Database\Migrations\Migration;

class CreatePaymentNetFunction extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        $sql = file_get_contents(__DIR__ . '/../sql/functions/calculate_payment_net/calculate_payment_net_v0001.sql');
        \DB::unprepared("DROP FUNCTION IF EXISTS calculate_payment_net");
        \DB::unprepared($sql);
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        \DB::unprepared("DROP FUNCTION IF EXISTS calculate_payment_net");
    }
}

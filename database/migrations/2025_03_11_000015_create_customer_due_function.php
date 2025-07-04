<?php

use Illuminate\Database\Migrations\Migration;

class CreateCustomerDueFunction extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        $sql = file_get_contents(__DIR__ . '/../sql/functions/calculate_customer_due/calculate_customer_due_v0001.sql');
        \DB::unprepared("DROP FUNCTION IF EXISTS calculate_customer_due");
        \DB::unprepared($sql);
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        DB::unprepared("DROP FUNCTION IF EXISTS calculate_customer_due");
    }
}

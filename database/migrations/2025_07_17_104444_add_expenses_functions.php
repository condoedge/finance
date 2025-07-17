<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddExpensesFunctions extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        $sql = file_get_contents(__DIR__ . '/../sql/functions/calculate_expense_report_amount_before_taxes/calculate_total_expense_report_amount_v0001.sql');
        DB::unprepared(processDelimiters($sql));

        $sql = file_get_contents(__DIR__ . '/../sql/functions/calculate_total_expense_report_amount/calculate_total_expense_report_amount_v0001.sql');
        DB::unprepared(processDelimiters($sql));
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        DB::unprepared("DROP FUNCTION IF EXISTS calculate_expense_report_amount_before_taxes");
        DB::unprepared("DROP FUNCTION IF EXISTS calculate_total_expense_report_amount");
    }
}

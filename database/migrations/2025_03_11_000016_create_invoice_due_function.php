<?php

use Illuminate\Database\Migrations\Migration;

class CreateInvoiceDueFunction extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        $sql = file_get_contents(__DIR__ . '/../sql/functions/calculate_invoice_due/calculate_invoice_due_v0001.sql');
        \DB::unprepared("DROP FUNCTION IF EXISTS calculate_invoice_due");
        \DB::unprepared($sql);
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        DB::unprepared("DROP FUNCTION IF EXISTS calculate_invoice_due");
    }
}

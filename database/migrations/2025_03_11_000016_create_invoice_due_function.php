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

        $sql = str_replace(
            'CREATE FUNCTION calculate_invoice_due(p_invoice_id INT) RETURNS DECIMAL(19,5)',
            'CREATE FUNCTION calculate_invoice_due(p_invoice_id INT) RETURNS DECIMAL(19,' . config('kompo-finance.payment-related-decimal-scale') . ')',
            $sql
        );
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

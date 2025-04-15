<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class SetInvoicesFunctions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $sql = file_get_contents(__DIR__ . '/../sql/functions/calculate_invoice_amount/calculate_invoice_amount_v0001.sql');
        \DB::unprepared("DROP FUNCTION IF EXISTS calculate_invoice_amount");
        \DB::unprepared($sql);

        $sql = file_get_contents(__DIR__ . '/../sql/functions/get_invoice_reference/get_invoice_reference_v0001.sql');
        \DB::unprepared("DROP FUNCTION IF EXISTS get_invoice_reference");
        \DB::unprepared($sql);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::unprepared("DROP FUNCTION IF EXISTS calculate_invoice_amount");
        DB::unprepared("DROP FUNCTION IF EXISTS get_invoice_reference");
    }
}

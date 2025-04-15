<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInvoiceDueFunction extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $sql = file_get_contents(__DIR__ . '/../sql/functions/calculate_invoice_due/calculate_invoice_due_v0001.sql');
        \DB::unprepared("DROP FUNCTION IF EXISTS calculate_invoice_due");
        \DB::unprepared($sql);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::unprepared("DROP FUNCTION IF EXISTS calculate_invoice_due");
    }
}

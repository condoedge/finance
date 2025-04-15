<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class SetInvoicesTriggers extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $sql = file_get_contents(__DIR__ . '/../sql/triggers/insert_address_for_invoice/insert_address_for_invoice_v0001.sql');
        \DB::unprepared("DROP TRIGGER IF EXISTS trg_insert_address_for_invoice");
        \DB::unprepared(processDelimiters($sql));

        $sql = file_get_contents(__DIR__ . '/../sql/triggers/insert_historical_customer/insert_historical_customer_v0001.sql');
        \DB::unprepared("DROP TRIGGER IF EXISTS trg_insert_historical_customer");
        \DB::unprepared(processDelimiters($sql));
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::unprepared("DROP TRIGGER IF EXISTS trg_insert_address_for_invoice");
        DB::unprepared("DROP TRIGGER IF EXISTS trg_insert_historical_customer");
    }
}

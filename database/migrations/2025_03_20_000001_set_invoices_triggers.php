<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class SetInvoicesTriggers extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        $sql = file_get_contents(__DIR__ . '/../sql/triggers/insert_address_for_invoice/insert_address_for_invoice_v0001.sql');
        DB::unprepared("DROP TRIGGER IF EXISTS trg_insert_address_for_invoice");
        DB::unprepared(processDelimiters($sql));

        $sql = file_get_contents(__DIR__ . '/../sql/triggers/insert_historical_customer/insert_historical_customer_v0001.sql');
        DB::unprepared("DROP TRIGGER IF EXISTS trg_insert_historical_customer");
        DB::unprepared(processDelimiters($sql));

        $sql = file_get_contents(__DIR__ . '/../sql/triggers/prevent_updating_deleting/prevent_updating_deleting_v0001.sql');
        DB::unprepared("DROP TRIGGER IF EXISTS prevent_update_invoice_customer");
        DB::unprepared("DROP TRIGGER IF EXISTS prevent_modification_fin_historical_customers");
        DB::unprepared("DROP TRIGGER IF EXISTS prevent_delete_fin_historical_customers");
        DB::unprepared(processDelimiters($sql));

        $sql = file_get_contents(__DIR__ . '/../sql/triggers/set_invoice_number/set_invoice_number_v0001.sql');
        DB::unprepared("DROP TRIGGER IF EXISTS tr_invoice_number_before_insert");
        DB::unprepared(processDelimiters($sql));

        $sql = file_get_contents(__DIR__ . '/../sql/triggers/ensure_invoice_payment_integrity/ensure_invoice_payment_integrity_v0001.sql');
        DB::unprepared("DROP TRIGGER IF EXISTS trg_ensure_invoice_payment_integrity");
        DB::unprepared(processDelimiters($sql));
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        DB::unprepared("DROP TRIGGER IF EXISTS trg_insert_address_for_invoice");
        DB::unprepared("DROP TRIGGER IF EXISTS trg_insert_historical_customer");
        DB::unprepared("DROP TRIGGER IF EXISTS prevent_updating_deleting");
        DB::unprepared("DROP TRIGGER IF EXISTS set_invoice_number");
    }
}

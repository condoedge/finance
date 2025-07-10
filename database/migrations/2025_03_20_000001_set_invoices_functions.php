<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class SetInvoicesFunctions extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        $sql = file_get_contents(__DIR__ . '/../sql/functions/calculate_customer_due/calculate_customer_due_v0001.sql');
        DB::unprepared("DROP FUNCTION IF EXISTS calculate_customer_due");
        DB::unprepared(processDelimiters($sql));

        $sql = file_get_contents(__DIR__ . '/../sql/functions/calculate_invoice_amount_before_taxes/calculate_invoice_amount_before_taxes_v0001.sql');
        DB::unprepared("DROP FUNCTION IF EXISTS calculate_invoice_amount_before_taxes");
        DB::unprepared(processDelimiters($sql));

        $sql = file_get_contents(__DIR__ . '/../sql/functions/calculate_invoice_due/calculate_invoice_due_v0001.sql');
        DB::unprepared("DROP FUNCTION IF EXISTS calculate_invoice_due");
        DB::unprepared(processDelimiters($sql));

        $sql = file_get_contents(__DIR__ . '/../sql/functions/calculate_invoice_status/calculate_invoice_status_v0001.sql');
        DB::unprepared("DROP FUNCTION IF EXISTS calculate_invoice_status");
        DB::unprepared(processDelimiters($sql));

        $sql = file_get_contents(__DIR__ . '/../sql/functions/calculate_invoice_tax/calculate_invoice_tax_v0001.sql');
        DB::unprepared("DROP FUNCTION IF EXISTS calculate_invoice_tax");
        DB::unprepared(processDelimiters($sql));

        $sql = file_get_contents(__DIR__ . '/../sql/functions/calculate_payment_amount_left/calculate_payment_amount_left_v0001.sql');
        $sql = str_replace(
            'CREATE FUNCTION calculate_payment_amount_left(p_payment_id INT) RETURNS DECIMAL(19,5)',
            'CREATE FUNCTION calculate_payment_amount_left(p_payment_id INT) RETURNS DECIMAL(19,' . config('kompo-finance.payment-related-decimal-scale') . ')',
            $sql
        );
        DB::unprepared("DROP FUNCTION IF EXISTS calculate_payment_amount_left");
        DB::unprepared(processDelimiters($sql));

        $sql = file_get_contents(__DIR__ . '/../sql/functions/get_amount_using_sign_from_invoice/get_amount_using_sign_from_invoice_v0001.sql');
        DB::unprepared("DROP FUNCTION IF EXISTS get_amount_using_sign_from_invoice");
        DB::unprepared(processDelimiters($sql));

        $sql = file_get_contents(__DIR__ . '/../sql/functions/get_amount_using_sign_multiplier/get_amount_using_sign_multiplier_v0001.sql');
        DB::unprepared("DROP FUNCTION IF EXISTS get_amount_using_sign_multiplier");
        DB::unprepared(processDelimiters($sql));

        $sql = file_get_contents(__DIR__ . '/../sql/functions/get_detail_tax_amount/get_detail_tax_amount_v0001.sql');
        DB::unprepared("DROP FUNCTION IF EXISTS get_detail_tax_amount");
        DB::unprepared(processDelimiters($sql));

        $sql = file_get_contents(__DIR__ . '/../sql/functions/get_detail_unit_price_with_sign/get_detail_unit_price_with_sign_v0001.sql');
        DB::unprepared("DROP FUNCTION IF EXISTS get_detail_unit_price_with_sign");
        DB::unprepared(processDelimiters($sql));

        $sql = file_get_contents(__DIR__ . '/../sql/functions/get_invoice_reference/get_invoice_reference_v0001.sql');
        DB::unprepared("DROP FUNCTION IF EXISTS get_invoice_reference");
        DB::unprepared(processDelimiters($sql));

        $sql = file_get_contents(__DIR__ . '/../sql/functions/get_payment_applied_amount_with_sign/get_payment_applied_amount_with_sign_v0001.sql');
        DB::unprepared("DROP FUNCTION IF EXISTS get_payment_applied_amount_with_sign");
        DB::unprepared(processDelimiters($sql));

        $sql = file_get_contents(__DIR__ . '/../sql/functions/get_updated_tax_amount_for_taxes/get_updated_tax_amount_for_taxes_v0001.sql');
        DB::unprepared("DROP FUNCTION IF EXISTS get_updated_tax_amount_for_taxes");
        DB::unprepared(processDelimiters($sql));

    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        DB::unprepared("DROP FUNCTION IF EXISTS calculate_customer_due");
        DB::unprepared("DROP FUNCTION IF EXISTS calculate_invoice_amount_before_taxes");
        DB::unprepared("DROP FUNCTION IF EXISTS calculate_invoice_due");
        DB::unprepared("DROP FUNCTION IF EXISTS calculate_invoice_status");
        DB::unprepared("DROP FUNCTION IF EXISTS calculate_invoice_tax");
        DB::unprepared("DROP FUNCTION IF EXISTS get_invoice_reference");
        DB::unprepared("DROP FUNCTION IF EXISTS get_detail_tax_amount");
        DB::unprepared("DROP FUNCTION IF EXISTS get_detail_unit_price_with_sign");
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A voided invoice said "paid", because voiding it settles the balance to zero and
 * calculate_invoice_status reads the balance. Anything trusting the column believed it —
 * Inscription::isPaid() among them.
 *
 * v0004 returns the 'cancelled' status, which fin_invoice_statuses has carried since the
 * start with nothing ever writing it. calculate_customer_due already excludes cancelled,
 * so the voiding credit note has to be excluded alongside its invoice or the pair counts
 * the cancellation twice and the customer reads as owed.
 *
 * Both changes are inert on existing data: they only fire on voided_at, which nothing has.
 */
return new class () extends Migration {
    public function up()
    {
        $functionsPath = __DIR__ . '/../sql/functions';

        DB::unprepared(processDelimiters(file_get_contents(
            $functionsPath . '/calculate_invoice_status/calculate_invoice_status_v0004.sql'
        )));

        DB::unprepared('DROP FUNCTION IF EXISTS calculate_customer_due');
        DB::unprepared(processDelimiters(file_get_contents(
            $functionsPath . '/calculate_customer_due/calculate_customer_due_v0002.sql'
        )));

        // No-op on the first run — nothing is voided yet. Kept so re-running after the
        // feature is live restates the statuses instead of leaving them behind.
        DB::statement('
            UPDATE fin_invoices
            SET invoice_status_id = calculate_invoice_status(id)
            WHERE voided_at IS NOT NULL
        ');
    }

    public function down()
    {
        $functionsPath = __DIR__ . '/../sql/functions';

        DB::unprepared(processDelimiters(file_get_contents(
            $functionsPath . '/calculate_invoice_status/calculate_invoice_status_v0003.sql'
        )));

        DB::unprepared('DROP FUNCTION IF EXISTS calculate_customer_due');
        DB::unprepared(processDelimiters(file_get_contents(
            $functionsPath . '/calculate_customer_due/calculate_customer_due_v0001.sql'
        )));
    }
};

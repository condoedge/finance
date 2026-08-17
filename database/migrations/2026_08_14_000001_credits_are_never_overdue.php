<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A credit note used to inherit a due date from its payment term, and
 * `is_invoice_overdue` was a bare `due_date < NOW()`, so every credit fell overdue
 * immediately and never recovered (its due only reaches 0 once applied).
 *
 * The v0003 function guards on the invoice type's sign, then this clears the due
 * dates already stored on credits and recalculates their status.
 */
return new class () extends Migration {
    public function up()
    {
        $functionsPath = __DIR__ . '/../sql/functions';

        DB::unprepared(processDelimiters(file_get_contents(
            $functionsPath . '/calculate_invoice_status/calculate_invoice_status_v0003.sql'
        )));

        DB::statement('
            UPDATE fin_invoices i
            JOIN fin_invoice_types t ON t.id = i.invoice_type_id
            SET i.invoice_due_date = NULL
            WHERE t.sign_multiplier < 0 AND i.invoice_due_date IS NOT NULL
        ');

        DB::statement('
            UPDATE fin_invoices i
            JOIN fin_invoice_types t ON t.id = i.invoice_type_id
            SET i.invoice_status_id = calculate_invoice_status(i.id)
            WHERE t.sign_multiplier < 0
        ');
    }

    public function down()
    {
        DB::unprepared(processDelimiters(file_get_contents(
            __DIR__ . '/../sql/functions/calculate_invoice_status/calculate_invoice_status_v0002.sql'
        )));
    }
};

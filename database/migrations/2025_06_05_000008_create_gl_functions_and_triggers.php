<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class CreateGlFunctionsAndTriggers extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        // Check if SQL files exist, otherwise use embedded SQL
        $functionsPath = __DIR__ . '/../sql/functions';
        $triggersPath = __DIR__ . '/../sql/triggers';

        // Load from files
        DB::unprepared(processDelimiters(file_get_contents($functionsPath . '/validate_fiscal_period_open/validate_fiscal_period_open_v0001.sql')));
        DB::unprepared(processDelimiters(file_get_contents($functionsPath . '/validate_gl_transaction_balance/validate_gl_transaction_balance_v0001.sql')));
        DB::unprepared(processDelimiters(file_get_contents($triggersPath . '/ensure_gl_transaction_integrity/ensure_gl_transaction_integrity_v0001.sql')));
        DB::unprepared(processDelimiters(file_get_contents($triggersPath . '/ensure_gl_line_integrity/ensure_gl_line_integrity_v0001.sql')));
        DB::unprepared(processDelimiters(file_get_contents($triggersPath . '/set_transaction_number/set_transaction_number_v0001.sql')));
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        // Drop triggers
        DB::unprepared('DROP TRIGGER IF EXISTS update_gl_header_balance_on_delete');
        DB::unprepared('DROP TRIGGER IF EXISTS update_gl_header_balance_on_update');
        DB::unprepared('DROP TRIGGER IF EXISTS update_gl_header_balance');
        DB::unprepared('DROP TRIGGER IF EXISTS ensure_gl_line_integrity');
        DB::unprepared('DROP TRIGGER IF EXISTS ensure_gl_transaction_integrity');
        DB::unprepared('DROP TRIGGER IF EXISTS tr_transaction_number_before_insert');

        // Drop functions
        DB::unprepared('DROP FUNCTION IF EXISTS validate_gl_transaction_balance');
        DB::unprepared('DROP FUNCTION IF EXISTS get_gl_transaction_out_of_balance_amount');

    }
}

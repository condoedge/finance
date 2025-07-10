<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddingTransactionFunctions extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('fin_gl_transaction_headers', function ($table) {
            // Add new columns for totals
            $table->decimal('total_debits', 19, config('kompo-finance.decimal-scale'))->default(0)->after('is_balanced');
            $table->decimal('total_credits', 19, config('kompo-finance.decimal-scale'))->default(0)->after('total_debits');
        });

        $functionsPath = __DIR__ . '/../sql/functions';

        // Load from files
        DB::unprepared(processDelimiters(file_get_contents($functionsPath . '/calculate_totals_transactions/calculate_totals_transactions_v0001.sql')));
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        DB::unprepared('DROP FUNCTION IF EXISTS calculate_total_debits');
        DB::unprepared('DROP FUNCTION IF EXISTS calculate_total_credits');
    }
}

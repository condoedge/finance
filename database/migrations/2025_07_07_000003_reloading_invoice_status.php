<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up()
    {
        $functionsPath = __DIR__ . '/../sql/functions';

        // Load from files
        DB::unprepared(processDelimiters(file_get_contents($functionsPath . '/calculate_invoice_due/calculate_invoice_due_v0001.sql')));
        DB::unprepared(processDelimiters(file_get_contents($functionsPath . '/calculate_invoice_status/calculate_invoice_status_v0002.sql')));
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        DB::unprepared('DROP FUNCTION IF EXISTS calculate_product_taxes_amount');
    }
};

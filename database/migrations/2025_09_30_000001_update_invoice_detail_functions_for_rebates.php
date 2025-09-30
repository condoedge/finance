<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class UpdateInvoiceDetailFunctionsForRebates extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        // Update the function to preserve raw unit_price values (allow negatives for rebates)
        $sql = file_get_contents(__DIR__ . '/../sql/functions/get_detail_unit_price_with_sign/get_detail_unit_price_with_sign_v0002.sql');
        DB::unprepared(processDelimiters($sql));
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        // Restore the original function that enforced sign correction
        DB::unprepared(file_get_contents(database_path('sql/functions/get_detail_unit_price_with_sign/get_detail_unit_price_with_sign_v0001.sql')));
    }
}
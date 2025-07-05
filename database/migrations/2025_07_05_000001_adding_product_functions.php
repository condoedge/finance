<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        $functionsPath = __DIR__ . '/../sql/functions';

        // Load from files
        DB::unprepared(processDelimiters(file_get_contents($functionsPath . '/calculate_product_taxes_amount/calculate_product_taxes_amount_v0001.sql')));
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        DB::unprepared('DROP FUNCTION IF EXISTS calculate_product_taxes_amount');
    }
};
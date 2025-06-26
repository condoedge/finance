<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Load and execute the SQL functions
        $sqlPath = __DIR__ . '/../sql/functions/account_building_functions/account_building_functions_v0001.sql';
        
        $sql = file_get_contents($sqlPath);
        DB::unprepared(processDelimiters($sql));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop functions
        DB::statement('DROP FUNCTION IF EXISTS get_account_segment_value(INT, INT) CASCADE');
        DB::statement('DROP FUNCTION IF EXISTS validate_account_segments(INT) CASCADE');
        DB::statement('DROP FUNCTION IF EXISTS build_account_descriptor(INT) CASCADE');
    }
};

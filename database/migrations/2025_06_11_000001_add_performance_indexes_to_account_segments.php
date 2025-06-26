<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add performance indexes for account segment assignments
        // Schema::table('fin_account_segment_assignments', function (Blueprint $table) {
        //     // Index for finding accounts by segment value
        //     $table->index('segment_value_id', 'idx_segment_value_lookup');
        // });
        
        // // Add performance indexes for segment values
        // Schema::table('fin_segment_values', function (Blueprint $table) {
        //     // Index for efficient lookups by definition and value
        //     $table->index(['segment_definition_id', 'segment_value'], 'idx_segment_def_value');
        // });
        
        // // Add performance indexes for GL accounts
        // Schema::table('fin_gl_accounts', function (Blueprint $table) {
        //     // Index for account type queries
        //     $table->index(['team_id', 'account_type', 'is_active'], 'idx_team_type_active');
            
        //     // Index for account ID lookups
        //     if (!$this->indexExists('fin_gl_accounts', 'idx_account_id')) {
        //         $table->index('account_id', 'idx_account_id');
        //     }
        // });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fin_account_segment_assignments', function (Blueprint $table) {
            $table->dropIndex('idx_account_segment_lookup');
            $table->dropIndex('idx_segment_value_lookup');
        });
        
        Schema::table('fin_segment_values', function (Blueprint $table) {
            $table->dropIndex('idx_segment_def_value');
            $table->dropIndex('idx_segment_def_active');
        });
        
        Schema::table('fin_gl_accounts', function (Blueprint $table) {
            $table->dropIndex('idx_team_type_active');
            if ($this->indexExists('fin_gl_accounts', 'idx_account_id')) {
                $table->dropIndex('idx_account_id');
            }
        });
    }
    
    /**
     * Check if an index exists
     */
    protected function indexExists(string $table, string $index): bool
    {
        $indexes = DB::select("SHOW INDEXES FROM {$table}");
        foreach ($indexes as $idx) {
            if ($idx->Key_name === $index) {
                return true;
            }
        }
        return false;
    }
};

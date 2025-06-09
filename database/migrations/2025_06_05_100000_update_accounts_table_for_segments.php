<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateAccountsTableForSegments extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Drop existing static segment columns and add dynamic segment support
        if (Schema::hasTable('fin_gl_accounts')) {
            Schema::table('fin_gl_accounts', function (Blueprint $table) {
                // Drop old static segment columns if they exist
                $columnsToCheck = ['segment_1_value', 'segment_2_value', 'segment_3_value', 'segment_4_value', 'segment_5_value'];
                foreach ($columnsToCheck as $column) {
                    if (Schema::hasColumn('fin_gl_accounts', $column)) {
                        $table->dropColumn($column);
                    }
                }
                
                // Add team_id if not exists
                if (!Schema::hasColumn('fin_gl_accounts', 'team_id')) {
                    $table->foreignId('team_id')->constrained('teams')->onDelete('cascade');
                }
                
                // Update account_type to use enum-like values
                if (Schema::hasColumn('fin_gl_accounts', 'account_type')) {
                    $table->string('account_type', 20)->change(); // ASSET, LIABILITY, EQUITY, REVENUE, EXPENSE
                }
                
                // Add indexes for performance
                $table->index(['team_id', 'is_active']);
                $table->index(['team_id', 'allow_manual_entry']);
                $table->index(['team_id', 'account_type']);
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasTable('fin_gl_accounts')) {
            Schema::table('fin_gl_accounts', function (Blueprint $table) {
                // Add back static segment columns
                $table->string('segment_1_value', 20)->nullable();
                $table->string('segment_2_value', 20)->nullable();
                $table->string('segment_3_value', 20)->nullable();
                $table->string('segment_4_value', 20)->nullable();
                $table->string('segment_5_value', 20)->nullable();
                
                // Drop team_id
                if (Schema::hasColumn('fin_gl_accounts', 'team_id')) {
                    $table->dropForeign(['team_id']);
                    $table->dropColumn('team_id');
                }
            });
        }
    }
}

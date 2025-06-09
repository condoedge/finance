<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTeamIdToFinanceTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Add team_id to accounts table if not exists
        if (Schema::hasTable('fin_gl_accounts') && !Schema::hasColumn('fin_gl_accounts', 'team_id')) {
            Schema::table('fin_gl_accounts', function (Blueprint $table) {
                $table->foreignId('team_id')->constrained('teams')->onDelete('cascade');
                
                // Update indexes to include team_id
                $table->index(['team_id', 'is_active', 'allow_manual_entry']);
                $table->index(['team_id', 'account_type']);
            });
        }

        // Add team_id to taxes table if not exists
        if (Schema::hasTable('fin_taxes') && !Schema::hasColumn('fin_taxes', 'team_id')) {
            Schema::table('fin_taxes', function (Blueprint $table) {
                $table->foreignId('team_id')->constrained('teams')->onDelete('cascade');
                
                $table->index(['team_id', 'valide_from', 'valide_to']);
            });
        }

        // Add team_id to tax groups table if not exists
        if (Schema::hasTable('fin_taxes_groups') && !Schema::hasColumn('fin_taxes_groups', 'team_id')) {
            Schema::table('fin_taxes_groups', function (Blueprint $table) {
                $table->foreignId('team_id')->constrained('teams')->onDelete('cascade');
                
                $table->index(['team_id']);
            });
        }

        // Add team_id to products table if not exists
        if (Schema::hasTable('fin_products') && !Schema::hasColumn('fin_products', 'team_id')) {
            Schema::table('fin_products', function (Blueprint $table) {
                $table->foreignId('team_id')->constrained('teams')->onDelete('cascade');
                
                $table->index(['team_id']);
            });
        }

        // Add team_id to transactions table if not exists
        if (Schema::hasTable('fin_transactions') && !Schema::hasColumn('fin_transactions', 'team_id')) {
            Schema::table('fin_transactions', function (Blueprint $table) {
                $table->foreignId('team_id')->constrained('teams')->onDelete('cascade');
                
                $table->index(['team_id', 'transaction_date']);
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
        // Remove team_id from finance tables
        $tables = ['fin_gl_accounts', 'fin_taxes', 'fin_taxes_groups', 'fin_products', 'fin_transactions'];
        
        foreach ($tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'team_id')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->dropForeign(['team_id']);
                    $blueprint->dropColumn('team_id');
                });
            }
        }
    }
}

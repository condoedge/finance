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
        // 1. Add team_id to accounts table
        Schema::table('fin_gl_accounts', function (Blueprint $table) {
            $table->foreignId('team_id')->nullable()->constrained('teams')->onDelete('cascade');
            $table->index(['team_id', 'is_active']);
        });

        // 2. Add team_id to taxes table
        Schema::table('fin_taxes', function (Blueprint $table) {
            $table->foreignId('team_id')->nullable()->constrained('teams')->onDelete('cascade');
            $table->index(['team_id', 'valide_from', 'valide_to']);
        });

        // 3. Add team_id to tax groups table
        Schema::table('fin_taxes_groups', function (Blueprint $table) {
            $table->foreignId('team_id')->nullable()->constrained('teams')->onDelete('cascade');
            $table->index(['team_id']);
        });

        // 4. Add team_id to products table
        Schema::table('fin_products', function (Blueprint $table) {
            $table->foreignId('team_id')->nullable()->constrained('teams')->onDelete('cascade');
            $table->index(['team_id']);
        });

        // 5. Add team_id to transactions table (if exists)
        if (Schema::hasTable('fin_transactions')) {
            Schema::table('fin_transactions', function (Blueprint $table) {
                $table->foreignId('team_id')->nullable()->constrained('teams')->onDelete('cascade');
                $table->index(['team_id', 'transaction_date']);
            });
        }

        // 6. Add team_id to entries table (if exists)
        if (Schema::hasTable('fin_entries')) {
            Schema::table('fin_entries', function (Blueprint $table) {
                $table->foreignId('team_id')->nullable()->constrained('teams')->onDelete('cascade');
                $table->index(['team_id', 'transaction_id']);
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
        $tables = [
            'fin_entries' => ['team_id'],
            'fin_transactions' => ['team_id'],
            'fin_products' => ['team_id'],
            'fin_taxes_groups' => ['team_id'],
            'fin_taxes' => ['team_id'],
            'fin_gl_accounts' => ['team_id'],
        ];

        foreach ($tables as $table => $columns) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $tableSchema) use ($columns) {
                    foreach ($columns as $column) {
                        $tableSchema->dropForeign([$column]);
                        $tableSchema->dropColumn($column);
                    }
                });
            }
        }
    }
}

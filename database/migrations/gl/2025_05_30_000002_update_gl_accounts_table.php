<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateGlAccountsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('fin_accounts', function (Blueprint $table) {
            // Drop existing name column and add new GL-specific fields
            $table->dropColumn('name');
            
            // Add GL Account specific fields
            $table->string('account_id')->unique()->after('id'); // e.g., '04-205-1105'
            $table->string('account_description')->after('account_id');
            $table->boolean('is_active')->default(true)->after('account_description');
            $table->boolean('allow_manual_entry')->default(true)->after('is_active');
            
            // Segment fields for account structure
            $table->string('segment1_value', 10)->nullable()->after('allow_manual_entry');
            $table->string('segment2_value', 10)->nullable()->after('segment1_value');
            $table->string('segment3_value', 10)->nullable()->after('segment2_value');
            $table->string('segment4_value', 10)->nullable()->after('segment3_value');
            $table->string('segment5_value', 10)->nullable()->after('segment4_value');
            
            // Account type and category
            $table->string('account_type')->nullable()->after('segment5_value'); // Asset, Liability, Equity, Revenue, Expense
            $table->string('account_category')->nullable()->after('account_type'); // More specific categorization
            
            // Add indexes for performance
            $table->index(['is_active']);
            $table->index(['allow_manual_entry']);
            $table->index(['account_type']);
            $table->index(['segment1_value', 'segment2_value', 'segment3_value']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('fin_accounts', function (Blueprint $table) {
            $table->dropIndex(['is_active']);
            $table->dropIndex(['allow_manual_entry']);
            $table->dropIndex(['account_type']);
            $table->dropIndex(['segment1_value', 'segment2_value', 'segment3_value']);
            
            $table->dropColumn([
                'account_id',
                'account_description',
                'is_active',
                'allow_manual_entry',
                'segment1_value',
                'segment2_value',
                'segment3_value',
                'segment4_value',
                'segment5_value',
                'account_type',
                'account_category'
            ]);
            
            $table->string('name')->after('id');
        });
    }
}

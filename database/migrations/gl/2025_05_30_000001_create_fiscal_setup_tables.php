<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFiscalSetupTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Fiscal Year Setup Table
        Schema::create('fin_fiscal_year_setup', function (Blueprint $table) {
            addMetaData($table, false);
            
            $table->id();
            $table->date('company_fiscal_start_date');
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            
            $table->timestamps();
        });

        // Fiscal Periods Table
        Schema::create('fin_fiscal_periods', function (Blueprint $table) {
            addMetaData($table, false);
            
            $table->string('period_id', 10)->primary(); // e.g., 'per01', 'per02'
            $table->integer('fiscal_year'); // e.g., 2025
            $table->integer('period_number'); // 1, 2, 3, etc.
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_open_gl')->default(true);
            $table->boolean('is_open_bnk')->default(true);
            $table->boolean('is_open_rm')->default(true);
            $table->boolean('is_open_pm')->default(true);
            
            $table->timestamps();
            
            $table->index(['fiscal_year', 'period_number']);
        });

        // Account Segment Definitions Table
        Schema::create('fin_account_segment_definitions', function (Blueprint $table) {
            addMetaData($table, false);
            
            $table->id();
            $table->integer('segment_position'); // 1, 2, 3, etc.
            $table->integer('segment_length'); // number of characters
            $table->string('segment_name'); // e.g., 'Project', 'Department', 'Account'
            $table->string('segment_description')->nullable();
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
            
            $table->unique('segment_position');
        });

        // GL Segment Values Table
        Schema::create('fin_gl_segment_values', function (Blueprint $table) {
            addMetaData($table, false);
            
            $table->id('segment_value_id');
            $table->integer('segment_type'); // 1 = structure definition, 2 = actual value
            $table->integer('segment_number'); // position of the segment
            $table->string('segment_value')->nullable(); // actual code (null for type 1)
            $table->string('segment_description');
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
            
            $table->index(['segment_type', 'segment_number']);
            $table->index(['segment_number', 'segment_value']);
        });

        // Company Default Accounts Table
        Schema::create('fin_company_default_accounts', function (Blueprint $table) {
            addMetaData($table, false);
            
            $table->string('setting_name')->primary(); // e.g., 'DefaultRevenueAccount'
            $table->string('account_id'); // FK to gl_accounts
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('fin_company_default_accounts');
        Schema::dropIfExists('fin_gl_segment_values');
        Schema::dropIfExists('fin_account_segment_definitions');
        Schema::dropIfExists('fin_fiscal_periods');
        Schema::dropIfExists('fin_fiscal_year_setup');
    }
}

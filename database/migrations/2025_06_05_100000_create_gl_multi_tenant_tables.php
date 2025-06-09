<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGlMultiTenantTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // 1. Fiscal Year Setup
        Schema::create('fin_fiscal_year_setup', function (Blueprint $table) {
            addMetaData($table);
            
            $table->date('company_fiscal_start_date')->comment('Start date of company fiscal year');
            $table->boolean('is_active')->default(true);
            
            // Multi-tenant support
            $table->foreignId('team_id')->constrained('teams')->onDelete('cascade');
            
            $table->index(['team_id', 'is_active']);
            $table->unique(['team_id', 'company_fiscal_start_date']);
        });

        // 2. Fiscal Periods 
        Schema::create('fin_fiscal_periods', function (Blueprint $table) {
            addMetaData($table);
            $table->string('period_id', 20)->index(); // e.g., 'per01', 'per02'
            $table->integer('fiscal_year'); // e.g., 2025
            $table->integer('period_number'); // 1-12
            $table->date('start_date');
            $table->date('end_date');
            
            // Multi-tenant support
            $table->foreignId('team_id')->constrained('teams')->onDelete('cascade');
            
            // Period status for different modules
            $table->boolean('is_open_gl')->default(true)->comment('Is period open for General Ledger');
            $table->boolean('is_open_bnk')->default(true)->comment('Is period open for Bank Reconciliation');
            $table->boolean('is_open_rm')->default(true)->comment('Is period open for Receivables Management');
            $table->boolean('is_open_pm')->default(true)->comment('Is period open for Payables Management');
            
            $table->index(['team_id', 'fiscal_year', 'period_number']);
            $table->unique(['team_id', 'period_id']);
        });

        // 3. GL Transaction Headers
        Schema::create('fin_gl_transaction_headers', function (Blueprint $table) {
            $table->string('gl_transaction_id', 50)->primary(); // YYYY-TT-NNNNNN format
            addMetaData($table);
            
            $table->date('fiscal_date');
            $table->integer('gl_transaction_type'); // 1=Manual GL, 2=AR, 3=AP, 4=BNK
            $table->string('transaction_description', 500);
            $table->boolean('is_posted')->default(false);
            $table->boolean('is_balanced')->default(false);
            
            // Multi-tenant support
            $table->foreignId('team_id')->constrained('teams')->onDelete('cascade');
            
            // Foreign key relationships
            $table->string('fiscal_period', 20)->nullable();
            $table->foreign('fiscal_period')->references('period_id')->on('fin_fiscal_periods');
            
            // Optional references to other modules
            $table->string('originating_module_transaction_id', 50)->nullable();
            $table->foreignId('customer_id')->nullable()->constrained('fin_customers');
            $table->integer('vendor_id')->nullable();
            
            // Indexes for performance
            $table->index(['team_id', 'fiscal_date']);
            $table->index(['team_id', 'gl_transaction_type']);
            $table->index(['team_id', 'is_posted']);
            $table->index(['team_id', 'fiscal_period']);
        });

        // 4. GL Transaction Lines
        Schema::create('fin_gl_transaction_lines', function (Blueprint $table) {
            $table->id();
            addMetaData($table);
            
            $table->string('gl_transaction_id', 50);
            $table->foreign('gl_transaction_id')->references('gl_transaction_id')->on('fin_gl_transaction_headers')->onDelete('cascade');
            
            $table->string('account_id', 20);
            $table->foreign('account_id')->references('account_id')->on('fin_gl_accounts');
            
            $table->string('line_description', 500)->nullable();
            $table->decimal('debit_amount', 19, config('kompo-finance.decimal-scale', 5))->default(0);
            $table->decimal('credit_amount', 19, config('kompo-finance.decimal-scale', 5))->default(0);
            
            // Multi-tenant support
            $table->foreignId('team_id')->constrained('teams')->onDelete('cascade');
            
            // Indexes for performance
            $table->index(['team_id', 'gl_transaction_id']);
            $table->index(['team_id', 'account_id']);
        });

        // 5. GL Sequences 
        Schema::create('fin_gl_sequences', function (Blueprint $table) {
            $table->id();
            addMetaData($table);
            
            $table->string('sequence_type', 50); // GL_TRANSACTION, INVOICE, etc.
            $table->integer('fiscal_year');
            $table->integer('next_number')->default(1);
            
            // Multi-tenant support  
            $table->foreignId('team_id')->constrained('teams')->onDelete('cascade');
            
            $table->unique(['team_id', 'sequence_type', 'fiscal_year']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('fin_gl_sequences');
        Schema::dropIfExists('fin_gl_transaction_lines');
        Schema::dropIfExists('fin_gl_transaction_headers');
        Schema::dropIfExists('fin_fiscal_periods');
        Schema::dropIfExists('fin_fiscal_year_setup');
    }
}

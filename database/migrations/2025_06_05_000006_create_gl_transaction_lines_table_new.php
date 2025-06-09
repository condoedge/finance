<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGlTransactionLinesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('fin_gl_transaction_lines', function (Blueprint $table) {
            addMetaData($table);
            
            // Reference to the header using ID since we use addMetaData
            $table->foreignId('gl_transaction_header_id')->constrained('fin_gl_transaction_headers');
            
            // Account and amounts
            $table->string('account_id', 20);
            $table->foreign('account_id')->references('account_id')->on('fin_gl_accounts');
            
            $table->decimal('debit_amount', 19, 5)->default(0);
            $table->decimal('credit_amount', 19, 5)->default(0);
            
            // Line description and analysis
            $table->string('line_description', 500)->nullable();
            $table->string('analysis_code_1', 50)->nullable();
            $table->string('analysis_code_2', 50)->nullable();
            $table->string('analysis_code_3', 50)->nullable();
            
            // Multi-tenant support
            $table->foreignId('team_id')->constrained('teams')->onDelete('cascade');
            
            // Indexes for performance
            $table->index(['team_id', 'account_id']);
            $table->index(['team_id', 'gl_transaction_header_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('fin_gl_transaction_lines');
    }
}

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
            
            // Link to header
            $table->foreignId('gl_transaction_id')
                  ->constrained('fin_gl_transaction_headers')
                  ->onDelete('cascade');
            
            // Account reference  
            $table->foreignId('account_id')->constrained('fin_gl_accounts')->onDelete('restrict');
            
            // Line details
            $table->string('line_description', 500)->nullable();
            $table->decimal('debit_amount', 19, config('kompo-finance.decimal-scale', 5))->default(0);
            $table->decimal('credit_amount', 19, config('kompo-finance.decimal-scale', 5))->default(0);
        
            
            // Multi-tenant support
            $table->foreignId('team_id')->constrained('teams')->onDelete('cascade');       
            
            // Indexes for performance
            $table->index(['team_id', 'account_id', 'created_at']);
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

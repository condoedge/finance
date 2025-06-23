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
            $table->id('gl_transaction_line_id');
            
            // Link to header
            $table->string('gl_transaction_id', 50);
            
            // Account reference  
            $table->foreignId('account_id')->constrained('fin_gl_accounts')->onDelete('restrict');
            
            // Line details
            $table->string('line_description', 500)->nullable();
            $table->decimal('debit_amount', 19, config('kompo-finance.decimal-scale', 5))->default(0);
            $table->decimal('credit_amount', 19, config('kompo-finance.decimal-scale', 5))->default(0);
            
            // Line sequence for ordering
            $table->unsignedSmallInteger('line_sequence')->default(1);
            
            // Multi-tenant support
            $table->foreignId('team_id')->constrained('teams')->onDelete('cascade');
            
            // Audit fields
            $table->foreignId('added_by')->nullable()->constrained('users');
            $table->foreignId('modified_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign key constraints
            $table->foreign('gl_transaction_id')
                  ->references('gl_transaction_id')
                  ->on('fin_gl_transaction_headers')
                  ->onDelete('cascade');
                  
            
            // Indexes for performance
            $table->index(['team_id', 'gl_transaction_id', 'line_sequence']);
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

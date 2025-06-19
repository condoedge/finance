<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CreateGlTransactionSequencesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('fin_gl_transaction_sequences', function (Blueprint $table) {
            addMetaData($table);
            $table->string('sequence_name', 50)->index(); // e.g., 'GL_TRANSACTION'
            $table->unsignedSmallInteger('fiscal_year'); // e.g., 2025
            $table->unsignedBigInteger('last_number')->default(0); // Last used number
            
            // Ensure unique sequence per fiscal year
            $table->unique(['sequence_name', 'fiscal_year']);        
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('fin_gl_transaction_sequences');
    }
}

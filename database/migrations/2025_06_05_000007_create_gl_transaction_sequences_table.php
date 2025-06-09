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
            $table->unique(['sequence_name', 'fiscal_year']);        });

        // Create the function to get next GL transaction number
        $sqlFunction = file_get_contents(database_path('sql/functions/get_next_gl_transaction_number/get_next_gl_transaction_number_v0002.sql'));
        DB::unprepared($sqlFunction);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::unprepared("DROP FUNCTION IF EXISTS get_next_gl_transaction_number;");
        Schema::dropIfExists('fin_gl_transaction_sequences');
    }
}

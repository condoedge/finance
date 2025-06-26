<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGlSequencesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('fin_gl_sequences', function (Blueprint $table) {
            addMetaData($table);
            
            $table->string('sequence_type', 50); // e.g., 'GL_TRANSACTION'
            $table->integer('fiscal_year');
            $table->integer('next_number')->default(1);
            
            // Multi-tenant support
            $table->foreignId('team_id')->constrained('teams')->onDelete('cascade');
            
            $table->index(['team_id', 'sequence_type', 'fiscal_year']);
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
    }
}

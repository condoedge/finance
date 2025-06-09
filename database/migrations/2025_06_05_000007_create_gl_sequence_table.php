<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CreateGlSequenceTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('fin_gl_sequences', function (Blueprint $table) {
            $table->string('sequence_type', 20)->primary(); // 'GL_TRANSACTION', 'JOURNAL_ENTRY', etc.
            $table->unsignedBigInteger('last_number')->default(0);
            $table->unsignedSmallInteger('fiscal_year')->nullable(); // If sequences reset per year
            
            $table->timestamps();
            
            // Ensure one sequence per type per year
            $table->unique(['sequence_type', 'fiscal_year']);
        });
        
        // Insert default sequences
        DB::table('fin_gl_sequences')->insert([
            [
                'sequence_type' => 'GL_TRANSACTION',
                'last_number' => 0,
                'fiscal_year' => null, // Global sequence
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);
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

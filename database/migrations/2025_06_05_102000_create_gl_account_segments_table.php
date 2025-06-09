<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGlAccountSegmentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {        Schema::create('fin_gl_account_segments', function (Blueprint $table) {
            addMetaData($table);
            
            $table->foreignId('segment_type')->constrained('fin_gl_account_segment_types');
            $table->unsignedTinyInteger('segment_number')->comment('Position of segment (1, 2, 3, etc.)');
            $table->string('segment_value', 20)->nullable()->comment('Actual code value (e.g., "04", "205") - null for structure definitions');
            $table->string('segment_description')->comment('Description of segment or value');
            
            // Multi-tenant support
            $table->foreignId('team_id')->constrained('teams')->onDelete('cascade');
            
            // Indexes for performance
            $table->index(['team_id', 'segment_type', 'segment_number']);
            $table->index(['team_id', 'segment_number', 'segment_value']);
            
            // Ensure unique combinations per team
            $table->unique(['team_id', 'segment_type', 'segment_number', 'segment_value'], 'unique_segment_combination');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('fin_gl_account_segments');
    }
}

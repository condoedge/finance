<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAccountSegmentValuesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('fin_account_segment_values', function (Blueprint $table) {
            addMetaData($table);
            
            $table->unsignedTinyInteger('segment_position'); // Links to fin_account_segment_definitions
            $table->string('segment_value', 20); // The actual code (e.g., '04', '205', '1105')
            $table->string('segment_description', 255); // Human-readable description
            $table->boolean('is_active')->default(true);
            
            // Foreign key constraint
            $table->foreign('segment_position')
                  ->references('segment_position')
                  ->on('fin_account_segment_definitions')
                  ->onDelete('cascade');
            
            // Ensure unique segment values per position
            $table->unique(['segment_position', 'segment_value']);
            $table->index(['segment_position', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('fin_account_segment_values');
    }
}

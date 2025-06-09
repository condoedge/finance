<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAccountSegmentDefinitionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('fin_account_segment_definitions', function (Blueprint $table) {
            $table->unsignedTinyInteger('segment_position')->primary(); // 1, 2, 3, etc.
            $table->unsignedTinyInteger('segment_length'); // Number of characters for this segment
            $table->string('segment_name', 50); // e.g., 'Project', 'Department', 'Natural Account'
            $table->string('segment_description', 255)->nullable();
            $table->boolean('is_required')->default(true);
            
            addedModifiedByColumns($table);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('fin_account_segment_definitions');
    }
}

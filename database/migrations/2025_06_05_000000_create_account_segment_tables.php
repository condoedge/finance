<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAccountSegmentTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Create account segment definitions table (segment_type = 1)
        Schema::create('fin_account_segments', function (Blueprint $table) {
            addMetaData($table);

            $table->string('segment_description');
            $table->unsignedTinyInteger('segment_position'); // 1, 2, 3, etc.
            $table->unsignedTinyInteger('segment_length'); // Number of characters for this segment
        });

        // Create account segment values table (segment_type = 2)
        Schema::create('fin_segment_values', function (Blueprint $table) {
            addMetaData($table);

            $table->varchar('segment_value', 20); // The actual code (e.g., '04', '205', '1105')
            $table->string('segment_description', 255); // Human-readable description

            $table->boolean('is_active')->default(true);
        });

        // Create account segment assignments table (the account build)
        Schema::create('fin_account_segment_assignments', function (Blueprint $table) {
            addMetaData($table);

            $table->foreignId('account_id')->constrained('fin_gl_accounts');
            $table->foreignId('segment_value_id')->constrained('fin_segment_values');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('fin_account_segments');
        Schema::dropIfExists('fin_segment_values');
    }
}

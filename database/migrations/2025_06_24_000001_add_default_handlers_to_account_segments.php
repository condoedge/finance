<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     *
     * Adds default handler functionality to account segments to enable
     * automatic value generation based on context (team, fiscal year, etc.)
     */
    public function up(): void
    {
        // Add default handler columns to segment definitions
        Schema::table('fin_account_segments', function (Blueprint $table) {
            $table->string('default_handler', 50)->nullable()
                ->after('segment_length')
                ->comment('Handler type for automatic value generation');

            $table->json('default_handler_config')->nullable()
                ->after('default_handler')
                ->comment('Configuration for the default handler');

            $table->index('default_handler');
        });

        // Create sequences table for sequence-based handlers
        Schema::create('fin_segment_sequences', function (Blueprint $table) {
            addMetadata($table);
            $table->string('sequence_key', 100)->comment('Unique identifier for the sequence');
            $table->unsignedBigInteger('team_id')->nullable()->comment('Team scope for sequence');
            $table->unsignedBigInteger('current_value')->default(0);
            $table->string('scope', 20)->default('global')->comment('Scope: global, team, parent_team');

            $table->unique(['sequence_key', 'team_id', 'scope']);
            $table->foreign('team_id')->references('id')->on('teams')->onDelete('cascade');
            $table->index(['sequence_key', 'scope']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fin_account_segments', function (Blueprint $table) {
            $table->dropIndex(['default_handler']);
            $table->dropColumn(['default_handler', 'default_handler_config']);
        });

        Schema::dropIfExists('fin_segment_sequences');
    }
};

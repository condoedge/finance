<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProperAccountSegmentSystem extends Migration
{
    /**
     * Run the migrations.
     * 
     * Implements the complete account segment system where:
     * - fin_account_segments: defines segment structure (position, length, description)  
     * - fin_segment_values: stores reusable segment values (10, 03, 4000, etc.)
     * - fin_account_segment_assignments: creates accounts by combining segment values
     * - fin_gl_accounts: accounts that reference these assignments
     */
    public function up()
    {
        // 1. Segment structure definitions
        Schema::create('fin_account_segments', function (Blueprint $table) {
            addMetadata($table);
            $table->string('segment_description'); // 'Parent Team', 'Team', 'Account'
            $table->unsignedTinyInteger('segment_position'); // 1, 2, 3
            $table->unsignedTinyInteger('segment_length'); // Character length for this segment
            
            // Ensure unique positions
            $table->unique('segment_position');
            $table->index('segment_position');
        });

        // 2. Reusable segment values (shared across teams/contexts)
        Schema::create('fin_segment_values', function (Blueprint $table) {
            addMetadata($table);
            $table->foreignId('segment_definition_id')->constrained('fin_account_segments')->onDelete('cascade');
            $table->string('segment_value', 20); // '10', '03', '4000'
            $table->string('segment_description', 255); // 'parent_team_10', 'team_03', 'cash_account'

            // If is the last segment in the account structure indicates account type
            // e.g. 1 for asset, 2 for liability, etc.
            $table->tinyInteger('account_type')->nullable();
            
            // Account management flags
            $table->boolean('is_active')->default(true);
            
            // Ensure unique values per segment definition
            $table->unique(['segment_definition_id', 'segment_value']);
            $table->index(['segment_definition_id', 'is_active']);
            $table->index('segment_value'); // For fast lookups
        });

        // 3. Account assignments (creates accounts from segment combinations)
        Schema::create('fin_account_segment_assignments', function (Blueprint $table) {
            addMetadata($table);
            $table->foreignId('account_id')->constrained('fin_gl_accounts')->onDelete('cascade');
            $table->foreignId('segment_value_id')->constrained('fin_segment_values')->onDelete('cascade');
            
            // Ensure no duplicate assignments
            $table->unique(['account_id', 'segment_value_id'], 'unique_account_segment_assignment');
            $table->index('account_id'); // For fast account lookups
            $table->index('segment_value_id'); // For segment usage tracking
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('fin_account_segment_assignments');
        Schema::dropIfExists('fin_segment_values');
        Schema::dropIfExists('fin_account_segments');
    }
}

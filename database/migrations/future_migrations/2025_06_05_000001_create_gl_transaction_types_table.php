<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create GL Transaction Types table
 * 
 * This migration creates the table-linked enum for GL transaction types
 * following the established pattern used by other enum tables in the system.
 */
class CreateGlTransactionTypesTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('fin_gl_transaction_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('label', 200);
            $table->string('code', 10)->unique(); // For ID generation (GL, BNK, AR, AP)
            $table->string('fiscal_period_field', 50); // Field name to check if period is open
            $table->boolean('allows_manual_entry')->default(false);
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            
            // Add indexes for performance
            $table->index(['is_active', 'sort_order']);
            $table->index('code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fin_gl_transaction_types');
    }
}

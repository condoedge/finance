<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create Payment Types table
 * 
 * CRITICAL: This migration MUST run before any table that references payment_type_id
 * This creates the table-linked enum for payment types following established patterns.
 */
class CreatePaymentTypesTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('fin_payment_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique(); // Internal enum name
            $table->string('label', 200); // Human-readable label
            $table->string('code', 10)->unique(); // Short code for references
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
        Schema::dropIfExists('fin_payment_types');
    }
}

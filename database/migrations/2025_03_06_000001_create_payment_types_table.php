<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create Payment Types table
 *
 * CRITICAL: This migration MUST run before any table that references payment_method_id
 * This creates the table-linked enum for payment types following established patterns.
 */
class CreatePaymentTypesTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('fin_payment_methods', function (Blueprint $table) {
            addMetadata($table);
            $table->string('name', 100)->unique(); // Internal enum name
            $table->string('code', 10)->unique(); // Short code for references
            $table->text('description')->nullable();

            $table->index('code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fin_payment_methods');
    }
}

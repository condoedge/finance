<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create Payment installments table
 */
class CreatePaymentInstallmentsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('fin_payment_installments', function (Blueprint $table) {
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
        Schema::dropIfExists('fin_payment_installments');
    }
}

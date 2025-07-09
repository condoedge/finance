<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create Payment terms table
 */
class CreatePaymentTermsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('fin_payment_terms', function (Blueprint $table) {
            addMetadata($table);
            $table->string('term_name', 100)->unique();
            $table->text('term_description')->nullable();

            $table->tinyInteger('term_type'); // Installment, COD, net 30, etc.

            $table->json('settings')->nullable(); // Additional settings for the term
        });

        Schema::table('fin_invoices', function (Blueprint $table) {
            $table->foreignId('payment_term_id')
                ->nullable()
                ->after('payment_method_id')
                ->constrained('fin_payment_terms');

            $table->json('possible_payment_terms')
                ->nullable()
                ->after('possible_payment_methods'); // New column for payment installment settings

            $table->dropForeign(['payment_installment_id']); // Drop foreign key constraint for old payment_installment_id
            $table->dropColumn('payment_installment_id'); // Remove old payment_installment_id column
            $table->dropColumn('possible_payment_installments'); // Remove old payment_installment_settings
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fin_payment_terms');
    }
}

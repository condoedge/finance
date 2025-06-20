<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInvoicesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('fin_invoices', function (Blueprint $table) {
            addMetaData($table);

            $table->foreignId('invoice_type_id')->constrained('fin_invoice_types');
            $table->unsignedBigInteger('invoice_number');

            $table->foreignId('invoice_status_id')->nullable()->constrained('fin_invoice_statuses');

            // BUILDED FROM invoice_type_id and invoice_number using get_invoice_reference function
            $table->string('invoice_reference')->nullable();

            $table->foreignId('account_receivable_id')->constrained('fin_gl_accounts');

            /**
             * @see Condoedge\Finance\Models\PaymentTypeEnum::class
            */
            $table->foreignId('payment_type_id')->constrained('fin_payment_types');

            $table->boolean('is_draft')->default(true);
            
            $table->timestamp('invoice_date');
            $table->timestamp('invoice_due_date')->nullable();
            $table->decimal('invoice_amount_before_taxes', 19, config('kompo-finance.decimal-scale'))->nullable();
            $table->decimal('invoice_total_amount', 19, config('kompo-finance.decimal-scale'))->storedAs('invoice_tax_amount + invoice_amount_before_taxes');

            // Redundant column to store the due amount
            $table->decimal('invoice_due_amount', 19, config('kompo-finance.decimal-scale'))->nullable();

            // Redundant column to store the tax amount
            $table->decimal('invoice_tax_amount', 19, config('kompo-finance.decimal-scale'))->nullable();

            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();

            $table->foreignId('historical_customer_id')->constrained('fin_historical_customers');
            $table->foreignId('customer_id')->constrained('fin_customers');

            $table->unique(['invoice_type_id', 'invoice_number'], 'fin_invoices_invoice_type_id_invoice_number_unique');
            $table->unique(['invoice_reference'], 'fin_invoices_invoice_reference_unique');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('fin_invoices');
    }
}

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

            // BUILDED FROM invoice_type_id and invoice_number using get_invoice_reference function
            $table->string('invoice_reference')->nullable();

            $table->foreignId('account_receivable_id')->constrained('fin_accounts');

            // We manage versions of taxes in the taxes table. So here we'll get the historical tax rate
            $table->foreignId('tax_group_id')->constrained('fin_taxes_groups');

            /**
             * @see Condoedge\Finance\Models\PaymentTypeEnum::class
            */
            $table->tinyInteger('payment_type_id');
            
            $table->date('invoice_date');
            $table->decimal('invoice_amount', 19, 5);

            // Redundant column to store the due amount
            $table->decimal('invoice_due_amount', 19, 5);

            // Redundant column to store the tax amount
            $table->decimal('invoice_tax_amount', 19, 5);

            $table->foreignId('customer_id')->constrained('fin_customers');
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

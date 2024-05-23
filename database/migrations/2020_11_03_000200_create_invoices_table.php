<?php

use Condoedge\Finance\Models\Invoice;
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
        Schema::create('invoices', function (Blueprint $table) {
            
            addMetaData($table);

            $table->foreignId('team_id')->constrained();
            $table->foreignId('customer_id')->constrained();
            $table->nullableMorphs('invoiceable');
            $table->tinyInteger('type')->default(Invoice::TYPE_PAYMENT);
            $table->decimal('calc_total_amount', 14, 2)->nullable();
            $table->decimal('calc_due_amount', 14, 2)->nullable();
            $table->foreignId('budget_id')->nullable()->constrained();
            $table->string('invoice_number');
            $table->string('status')->default(1);
            $table->date('invoiced_at');
            $table->dateTime('due_at')->useCurrent();
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->dateTime('approved_at')->nullable();

            $table->foreignId('sent_by')->nullable()->constrained('users');
            $table->dateTime('sent_at')->nullable();
            $table->text('notes')->nullable();
        });

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->foreignId('invoice_id')->nullable()->constrained();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropForeign(['invoice_id']);
                $table->dropColumn('invoice_id');
            });
        }

        Schema::dropIfExists('invoices');
    }
}

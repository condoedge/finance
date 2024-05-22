<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transactions', function (Blueprint $table) {
            
            addMetaData($table);

            $table->date('transacted_at')->nullable();
            $table->decimal('amount', 14, 2);
            $table->string('type');
            $table->tinyInteger('void')->nullable();
            $table->foreignId('union_id')->constrained();
            $table->foreignId('user_id')->nullable()->constrained();
            $table->foreignId('invoice_id')->nullable()->constrained();
            $table->foreignId('bill_id')->nullable()->constrained();
            $table->foreignId('cn_invoice_id')->nullable()->constrained('invoices');
            $table->foreignId('cn_bill_id')->nullable()->constrained('bills');
            $table->text('description')->nullable();
            $table->boolean('reconciled')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('transactions');
    }
}

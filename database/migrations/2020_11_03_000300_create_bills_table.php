<?php

use Condoedge\Finance\Models\Bill;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBillsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('bills', function (Blueprint $table) {
            
            addMetaData($table);

            $table->foreignId('team_id')->constrained();
            $table->foreignId('supplier_id')->constrained();
            $table->string('bill_number')->nullable();
            $table->tinyInteger('type')->default(Bill::TYPE_PAYMENT);
            $table->string('status')->default(Bill::STATUS_RECEIVED);
            $table->foreignId('recurrence_id')->nullable()->constrained();
            $table->decimal('calc_total_amount', 14, 2)->nullable();
            $table->decimal('calc_due_amount', 14, 2)->nullable();
            $table->dateTime('worked_at')->nullable();
            $table->dateTime('billed_at')->nullable();
            $table->dateTime('due_at');
            $table->string('supplier_number')->nullable();
            $table->text('notes')->nullable();

            $table->dateTime('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('bills');
    }
}

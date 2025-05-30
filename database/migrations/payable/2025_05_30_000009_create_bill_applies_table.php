<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBillAppliesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('fin_bill_applies', function (Blueprint $table) {
            addMetaData($table);

            $table->foreignId('bill_id')->constrained('fin_bills');
            $table->date('apply_date');
            
            // Polymorphic relationship to applicable records (payments, credits, etc.)
            $table->unsignedBigInteger('applicable_id');
            $table->integer('applicable_type');
            
            $table->decimal('payment_applied_amount', 19, config('kompo-finance.decimal-scale'));
            
            $table->index(['bill_id']);
            $table->index(['applicable_id', 'applicable_type']);
            $table->index(['apply_date']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('fin_bill_applies');
    }
}

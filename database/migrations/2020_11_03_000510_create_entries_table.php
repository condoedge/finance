<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEntriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('entries', function (Blueprint $table) {
            
            addMetaData($table);

            $table->foreignId('transaction_id')->constrained();
            $table->foreignId('gl_account_id')->constrained();
            $table->string('payment_method')->default(1);
            $table->text('description')->nullable();
            $table->dateTime('transacted_at')->useCurrent();
            $table->decimal('credit', 14, 2);
            $table->decimal('debit', 14, 2);
            $table->tinyInteger('unvoid_flag')->nullable();
            $table->string('payment_number')->nullable();
            $table->date('reconciled_during')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('entries');
    }
}

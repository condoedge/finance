<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateConciliationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('conciliations', function (Blueprint $table) {
            
            addMetaData($table);

            $table->foreignId('gl_account_id')->constrained();
            $table->date('reconciled_at');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->decimal('opening_balance', 14, 2)->nullable();
            $table->decimal('closing_balance', 14, 2)->nullable();
            $table->decimal('resolved', 14, 2)->nullable();
            $table->decimal('remaining', 14, 2)->nullable();
            $table->text('reconciled_ids')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('conciliations');
    }
}

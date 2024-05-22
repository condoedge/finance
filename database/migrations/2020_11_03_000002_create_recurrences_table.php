<?php

use Condoedge\Finance\Models\Recurrence;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRecurrencesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('recurrences', function (Blueprint $table)
        {
            addMetaData($table);
            
            $table->foreignId('team_id')->constrained();
            $table->foreignId('union_id')->nullable()->constrained();
            $table->foreignId('user_id')->index()->nullable();
            
            $table->integer('recu_period')->nullable();
            $table->date('recu_start')->nullable();
            $table->date('recu_end')->nullable();

            $table->integer('child_type')->default(Recurrence::CHILD_BILL);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('recurrences');
    }
}

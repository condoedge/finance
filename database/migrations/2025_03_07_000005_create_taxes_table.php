<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTaxesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('fin_taxes', function (Blueprint $table) {
            addMetaData($table);
            
            $table->string('name');
            $table->decimal('rate', 10, 6);
            $table->foreignId('account_id')->nullable()->constrained('fin_gl_accounts');
            $table->date('valide_from');
            $table->date('valide_to')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('fin_taxes');
    }
}

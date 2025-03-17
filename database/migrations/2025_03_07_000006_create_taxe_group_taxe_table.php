<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTaxeGroupTaxeTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('fin_taxes_group_taxes', function (Blueprint $table) {
            addMetaData($table);
            
            $table->foreignId('tax_group_id')->constrained('fin_taxes_groups');
            $table->foreignId('tax_id')->constrained('fin_taxes');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('fin_taxes_group_taxes');
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRgcqsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('rgcqs', function (Blueprint $table) {
            
            addMetaData($table);

            $table->integer('level');
            $table->integer('group');
            $table->string('code');
            $table->integer('fund_type_id')->nullable();
            $table->string('type');
            $table->string('name')->nullable();
            $table->string('subname')->nullable();
            $table->text('description')->nullable();
            $table->tinyInteger('enabled')->default(1);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('rgcqs');
    }
}

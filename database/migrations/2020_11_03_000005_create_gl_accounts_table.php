<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGlAccountsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gl_accounts', function (Blueprint $table) {

            addMetaData($table);

            $table->foreignId('union_id')->constrained();
            $table->foreignId('bank_id')->nullable()->constrained();
            $table->foreignId('fund_id')->nullable()->constrained();
            $table->foreignId('fund2_id')->nullable()->constrained('funds');
            $table->foreignId('tax_id')->nullable()->constrained();
            
            $table->integer('level');
            $table->integer('group');
            $table->string('code');
            $table->string('type');
            $table->string('name')->nullable();
            $table->string('subname')->nullable();
            $table->text('description')->nullable();

            $table->string('number')->nullable();
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
        Schema::dropIfExists('gl_accounts');
    }
}

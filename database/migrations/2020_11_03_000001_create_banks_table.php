<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBanksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('banks', function (Blueprint $table) {
            
            addMetaData($table);

            $table->foreignId('team_id')->nullable()->constrained(); //For union accounting reconciliation
            $table->foreignId('user_id')->nullable()->constrained(); //owner contribution withdrawal - self registration
            $table->foreignId('customer_id')->nullable()->constrained(); //in case we need it => owner contribution withdrawal - registered by management team
            $table->string('name');
            $table->string('institution')->nullable();
            $table->string('branch')->nullable();
            $table->string('account_number')->nullable();
            $table->text('note')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->tinyInteger('default_bank')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('banks');
    }
}

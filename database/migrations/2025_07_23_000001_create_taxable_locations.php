<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('fin_taxable_locations', function (Blueprint $table) {
            addMetaData($table);

            $table->string('name', 255)->nullable();
            $table->string('code', 50)->nullable();
            $table->tinyInteger('type')->default(1)->comment('1: Province, 2: Territory');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('fin_webhook_events');
    }
};

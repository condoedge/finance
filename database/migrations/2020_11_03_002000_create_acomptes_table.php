<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAcomptesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('acomptes', function (Blueprint $table) {
            
            addMetaData($table);

            $table->foreignId('customer_id')->nullable()->constrained();
            $table->foreignId('transaction_id')->nullable()->constrained();
            $table->decimal('amount', 14, 2);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('acomptes');
    }
}

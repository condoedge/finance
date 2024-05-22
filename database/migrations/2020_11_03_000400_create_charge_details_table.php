<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateChargeDetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {       
        Schema::create('charge_details', function (Blueprint $table) {
            
            addMetaData($table);

            $table->foreignId('invoice_id')->nullable()->constrained();
            $table->foreignId('bill_id')->nullable()->constrained();
            $table->foreignId('gl_account_id')->nullable()->constrained();
            $table->foreignId('fund_id')->nullable()->constrained();

            $table->string('name_chd')->nullable();
            $table->decimal('quantity_chd', 7, 2)->nullable();
            $table->decimal('price_chd', 14, 2)->nullable();
            $table->text('description_chd')->nullable();

            $table->decimal('pretax_amount_chd', 14, 2)->nullable();
            $table->decimal('tax_amount_chd', 14, 2)->nullable();
            $table->decimal('total_amount_chd', 14, 2)->nullable();

            $table->nullableMorphs('chargeable');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('charge_details');
    }
}

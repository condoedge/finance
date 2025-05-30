<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateHistoricalVendorsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('fin_historical_vendors', function (Blueprint $table) {
            addMetaData($table);

            $table->foreignId('vendor_id')->constrained('fin_vendors');
            $table->string('name');
            $table->foreignId('default_billing_address_id')->nullable()->constrained('fin_addresses');
            $table->integer('default_payment_type_id')->nullable();
            $table->foreignId('default_tax_group_id')->nullable()->constrained('fin_tax_groups');
            
            // Customable fields
            $table->string('customable_type')->nullable();
            $table->unsignedBigInteger('customable_id')->nullable();
            
            $table->index(['vendor_id']);
            $table->index(['customable_type', 'customable_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('fin_historical_vendors');
    }
}

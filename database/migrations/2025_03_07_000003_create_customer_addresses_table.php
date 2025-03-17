<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCustomerAddressesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('fin_customer_addresses', function (Blueprint $table) {
            addMetaData($table);

            $table->string('name')->nullable();
            $table->string('address');
            $table->string('city');
            $table->string('state');
            $table->string('country');
            $table->string('postal_code');

            $table->foreignId('customer_id')->constrained('fin_customers');
            $table->foreignId('default_tax_group_id')->nullable()->constrained('fin_taxes_groups');
        });

        Schema::table('fin_customers', function (Blueprint $table) {
            $table->foreign('default_billing_address_id')->references('id')->on('fin_customer_addresses');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('fin_customers', function (Blueprint $table) {
            $table->dropForeign(['default_billing_address_id']);
        });

        Schema::dropIfExists('fin_customer_addresses');
    }
}

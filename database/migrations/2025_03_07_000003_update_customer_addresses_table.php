<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateCustomerAddressesTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->foreignId('default_tax_group_id')->nullable()->constrained('fin_taxes_groups');
        });

        Schema::table('fin_customers', function (Blueprint $table) {
            $table->foreign('default_billing_address_id')->references('id')->on('addresses');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('fin_customers', function (Blueprint $table) {
            $table->dropForeign(['default_billing_address_id']);
        });

        Schema::table('addresses', function (Blueprint $table) {
            $table->dropForeign(['default_tax_group_id']);
            $table->dropColumn('default_tax_group_id');
        });
    }
}

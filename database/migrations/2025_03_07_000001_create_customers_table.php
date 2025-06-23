<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCustomersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {        Schema::create('fin_customers', function (Blueprint $table) {
            addMetaData($table);

            $table->string('name');
            $table->foreignId('default_payment_method_id')->nullable()->constrained('fin_payment_methods');
            $table->unsignedBigInteger('default_billing_address_id')->nullable();

            $table->foreignId('team_id')->constrained('teams');

            // Just used to make the links between the customable model and the customer
            // It will be used to update the customable model when the customer is updated
            // The idea is not need to be used in queries, just to make the savings
            $table->nullableMorphs('customable');

            // Precalculated fields
            $table->decimal('customer_due_amount', 19, config('kompo-finance.decimal-scale'))->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('fin_customers');
    }
}

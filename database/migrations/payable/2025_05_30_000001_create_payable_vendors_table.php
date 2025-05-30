<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePayableVendorsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('fin_vendors', function (Blueprint $table) {
            addMetaData($table);

            $table->string('name');
            $table->decimal('vendor_due_amount', 19, config('kompo-finance.decimal-scale'))->default(0);
            $table->foreignId('default_billing_address_id')->nullable()->constrained('fin_addresses');
            $table->integer('default_payment_type_id')->nullable();
            $table->foreignId('default_tax_group_id')->nullable()->constrained('fin_tax_groups');
            
            // Customable fields
            $table->string('customable_type')->nullable();
            $table->unsignedBigInteger('customable_id')->nullable();
            
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
        Schema::dropIfExists('fin_vendors');
    }
}

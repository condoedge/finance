<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVendorsTable extends Migration
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
            
            $table->string('vendor_code')->unique();
            $table->string('vendor_name');
            $table->string('contact_person')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('tax_id')->nullable();
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
            
            $table->index(['is_active']);
            $table->index(['vendor_code']);
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

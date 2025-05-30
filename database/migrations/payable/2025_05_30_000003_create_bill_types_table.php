<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBillTypesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('fin_bill_types', function (Blueprint $table) {
            addMetaData($table, false);
            
            $table->id();
            $table->string('name');
            $table->string('prefix', 10);
            $table->integer('sign_multiplier')->default(1); // 1 for bill, -1 for credit
            $table->boolean('is_active')->default(true);
        });

        // Insert default bill types
        DB::table('fin_bill_types')->insert([
            ['id' => 1, 'name' => 'Bill', 'prefix' => 'BILL', 'sign_multiplier' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Credit', 'prefix' => 'BCRD', 'sign_multiplier' => -1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('fin_bill_types');
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBillStatusesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('fin_bill_statuses', function (Blueprint $table) {
            addMetaData($table, false);
            
            $table->id();
            $table->string('name');
            $table->boolean('can_be_paid')->default(false);
            $table->boolean('is_active')->default(true);
        });

        // Insert default bill statuses
        DB::table('fin_bill_statuses')->insert([
            ['id' => 1, 'name' => 'Draft', 'can_be_paid' => false, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Pending', 'can_be_paid' => true, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'name' => 'Paid', 'can_be_paid' => false, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'name' => 'Cancelled', 'can_be_paid' => false, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('fin_bill_statuses');
    }
}

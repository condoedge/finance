<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('fin_rebates', function (Blueprint $table) {
            addMetaData($table);

            $table->string('name', 255)->nullable(); // It won't be used for now because it's not in the design, but just in case

            $table->string('rebate_logic_type', 255); // This will be used to determine which handler to use for the rebate
            $table->json('rebate_logic_parameters')->nullable(); // This will store any parameters needed for the rebate logic, it can be used for example to store a minimum quantity for the rebate to apply
            $table->foreignId('product_id')->constrained('fin_products')->onDelete('cascade');
            $table->tinyInteger('amount_type'); // 1 for percentage, 2 for fixed amount
            $table->decimal('amount', 14, 2);

            $table->boolean('is_accumulable')->default(false); // If true, this rebate can be accumulated with other rebates, if false, only the best rebate will be applied
            $table->integer('order')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('fix_rebates');
    }
};

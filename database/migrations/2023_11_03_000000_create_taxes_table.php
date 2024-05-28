<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTaxesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('taxes', function (Blueprint $table) {
            
            addMetaData($table);

            $table->foreignId('team_id')->constrained();
            $table->string('name');
            $table->decimal('rate', 10, 6);
            $table->text('notes')->nullable();
            $table->tinyInteger('type')->default(1);
            $table->tinyInteger('enabled')->default(1);
        });

        Schema::create('taxable_tax', function (Blueprint $table) {
            $table->id();
            $table->morphs('taxable');
            $table->foreignId('tax_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('taxable_tax');
        Schema::dropIfExists('taxes');
    }
}

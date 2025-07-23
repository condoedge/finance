<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('fin_taxes', function (Blueprint $table) {
            $table->foreignId('taxable_location_id')
                ->nullable()
                ->constrained('fin_taxable_locations')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('fin_taxes', function (Blueprint $table) {
            $table->dropForeign(['taxable_location_id']);
            $table->dropColumn('taxable_location_id');
        });
    }
};
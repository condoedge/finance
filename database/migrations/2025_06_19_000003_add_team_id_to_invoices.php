<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTeamIdToInvoices extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('fin_invoices', function (Blueprint $table) {
            $table->foreignId('team_id')
                  ->nullable()
                  ->constrained('teams');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('fin_invoices', function (Blueprint $table) {
            $table->dropForeign(['team_id']);
            $table->dropColumn('team_id');
        });
    }
}

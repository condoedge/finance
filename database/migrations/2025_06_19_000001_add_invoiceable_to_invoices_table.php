<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddInvoiceableToInvoicesTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('fin_invoices', function (Blueprint $table) {
            $table->nullableMorphs('invoiceable');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('fin_invoices', function (Blueprint $table) {
            $table->dropMorphs('invoiceable');
        });
    }
}

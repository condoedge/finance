<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateGlEntriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('fin_entries', function (Blueprint $table) {
            // Add GL Entry specific fields
            $table->string('line_description')->nullable()->after('account_id');
            
            // Rename foreign key column to match GL convention
            $table->renameColumn('transaction_id', 'gl_transaction_id');
            
            // Add index for performance
            $table->index(['gl_transaction_id', 'account_id']);
        });
        
        // Update foreign key constraint
        Schema::table('fin_entries', function (Blueprint $table) {
            $table->dropForeign(['transaction_id']);
            $table->foreign('gl_transaction_id')->references('id')->on('fin_transactions');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('fin_entries', function (Blueprint $table) {
            $table->dropForeign(['gl_transaction_id']);
            $table->dropIndex(['gl_transaction_id', 'account_id']);
            
            $table->renameColumn('gl_transaction_id', 'transaction_id');
            $table->dropColumn('line_description');
            
            $table->foreign('transaction_id')->references('id')->on('fin_transactions');
        });
    }
}

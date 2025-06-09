<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCompanyDefaultAccountsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('fin_company_default_accounts', function (Blueprint $table) {
            addMetaData($table);
            
            $table->foreignId('team_id')->constrained('teams')->onDelete('cascade');
            $table->string('setting_name', 100); // e.g., 'DefaultRevenueAccount', 'DefaultExpenseAccount'
            $table->string('account_id', 50); // FK to fin_gl_accounts
            $table->string('description', 255)->nullable();
            
            $table->unique(['team_id', 'setting_name']);
            $table->index(['team_id']);
            
            // Add foreign key to accounts table
            $table->foreign('account_id')->references('account_id')->on('fin_gl_accounts');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('fin_company_default_accounts');
    }
}

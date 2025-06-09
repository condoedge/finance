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
            $table->string('setting_name', 100)->primary(); // e.g., 'default_revenue_account'
            $table->string('account_id', 50);
            $table->string('description', 255)->nullable();
            
            addedModifiedByColumns($table);
            $table->timestamps();
            
            // Foreign key to accounts
            $table->foreign('account_id')
                  ->references('account_id')
                  ->on('fin_gl_accounts')
                  ->onDelete('restrict');
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

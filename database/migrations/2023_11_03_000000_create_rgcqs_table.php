<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRgcqsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::dropIfExists('conciliations');
        Schema::dropIfExists('entries');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('charge_details');
        Schema::dropIfExists('bills');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('sub_accounts');
        Schema::dropIfExists('budgets');
        Schema::dropIfExists('account_balances');
        Schema::dropIfExists('gl_accounts');
        Schema::dropIfExists('funds');
        Schema::dropIfExists('recurrences');
        Schema::dropIfExists('banks');
        Schema::dropIfExists('taxable_tax');
        Schema::dropIfExists('taxes');
        Schema::dropIfExists('rgcqs');

        Schema::create('rgcqs', function (Blueprint $table) {
            
            addMetaData($table);

            $table->integer('group');
            $table->string('code');
            $table->integer('fund_type_id')->nullable();
            $table->string('type');
            $table->string('name')->nullable();
            $table->string('subname')->nullable();
            $table->text('description')->nullable();
            $table->tinyInteger('enabled')->default(1);
        });

        /*
        INSERT INTO `rgcqs` (`id`, `level`, `group`, `code`, `fund_type_id`, `type`, `name`, `subname`, `description`, `enabled`, `created_at`, `updated_at`, `deleted_at`) 
        VALUES (NULL, '1', '1', '1100', NULL, '{\"fr\":\"Encaisse\",\"en\":\"Bank\"}', NULL, NULL, NULL, '1', now(), now(), NULL), 
        (NULL, '1', '1', '1200', NULL, '{\"fr\":\"Comptes à recevoir\",\"en\":\"Accounts receivable\"}', NULL, NULL, NULL, '1', now(), now(), NULL), 
        (NULL, '1', '2', '2200', NULL, '{\"fr\":\"Comptes à payer\",\"en\":\"Accounts payable\"}', NULL, NULL, NULL, '1', now(), now(), NULL), 
        (NULL, '2', '2', '2410', NULL, '{\"fr\":\"Sommes dues aux copropriétaires\",\"en\":\"Amounts due to co-owners\"}', '{\"fr\":\"Contributions perçues d\'avance\",\"en\":\"Contributions received in advance\"}', NULL, NULL, '1', now(), now(), NULL), 
        (NULL, '1', '5', '3100', '1', '{\"fr\":\"Surplus du Fonds d\'exploitation\",\"en\":\"Surplus of the Operating fund\"}', NULL, NULL, NULL, '1', now(), now(), NULL), 
        (NULL, '1', '3', '4100', NULL, '{\"fr\":\"Contributions\",\"en\":\"Contributions\"}', NULL, NULL, NULL, '1', now(), now(), NULL), 
        (NULL, '1', '3', '4500', '1', '{\"fr\":\"Revenus d\'intérêts\",\"en\":\"Interest income\"}', NULL, NULL, NULL, '1', now(), now(), NULL), 
        (NULL, '2', '3', '4590', '1', '{\"fr\":\"Revenus d\'intérêts\",\"en\":\"Interest income\"}', '{\"fr\":\"Paiements retardataires\",\"en\":\"Interests on late payments\"}', NULL, NULL, '1', now(), now(), NULL), 
        (NULL, '2', '3', '4630', '1', '{\"fr\":\"Frais aux copropriétaires\",\"en\":\"Fees to co-owners\"}', '{\"fr\":\"Insuffisance de fonds\",\"en\":\"Fees for insufficient funds\"}', NULL, NULL, '1', now(), now(), NULL), 
        (NULL, '1', '4', '5700', '1', '{\"fr\":\"Intérêts\",\"en\":\"Interests\"}', NULL, NULL, NULL, '1', now(), now(), NULL)*/

        
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('rgcqs');
    }
}

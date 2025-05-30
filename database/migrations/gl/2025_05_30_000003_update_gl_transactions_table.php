<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateGlTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('fin_transactions', function (Blueprint $table) {
            // Drop existing fields
            $table->dropColumn(['transaction_date', 'transaction_source']);
            
            // Add GL Transaction specific fields
            $table->bigInteger('gl_transaction_number')->unique()->after('id'); // Sequential, unbroken number
            $table->date('fiscal_date')->after('gl_transaction_number');
            $table->integer('fiscal_year')->after('fiscal_date');
            $table->string('fiscal_period', 10)->after('fiscal_year'); // Reference to period_id
            $table->integer('transaction_type')->after('fiscal_period'); // 1=Manual, 2=Bank, 3=Receivable, 4=Payable
            $table->string('transaction_description')->after('transaction_type');
            
            // Links to originating modules
            $table->string('originating_module_transaction_id')->nullable()->after('transaction_description');
            $table->foreignId('vendor_id')->nullable()->constrained('fin_vendors')->after('originating_module_transaction_id');
            $table->foreignId('customer_id')->nullable()->constrained('fin_customers')->after('vendor_id');
            $table->bigInteger('team_id')->nullable()->after('customer_id'); // Reference to teams table
            
            // Audit fields
            $table->string('created_by')->after('team_id');
            $table->timestamp('created_at')->nullable()->change();
            $table->string('modified_by')->nullable()->after('created_by');
            $table->timestamp('modified_at')->nullable()->after('modified_by');
            
            // Add indexes
            $table->index(['fiscal_date']);
            $table->index(['fiscal_year', 'fiscal_period']);
            $table->index(['transaction_type']);
            $table->index(['customer_id']);
            $table->index(['vendor_id']);
            
            // Foreign key for fiscal period
            $table->foreign('fiscal_period')->references('period_id')->on('fin_fiscal_periods');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('fin_transactions', function (Blueprint $table) {
            $table->dropForeign(['fiscal_period']);
            $table->dropForeign(['vendor_id']);
            $table->dropForeign(['customer_id']);
            
            $table->dropIndex(['fiscal_date']);
            $table->dropIndex(['fiscal_year', 'fiscal_period']);
            $table->dropIndex(['transaction_type']);
            $table->dropIndex(['customer_id']);
            $table->dropIndex(['vendor_id']);
            
            $table->dropColumn([
                'gl_transaction_number',
                'fiscal_date',
                'fiscal_year',
                'fiscal_period',
                'transaction_type',
                'transaction_description',
                'originating_module_transaction_id',
                'vendor_id',
                'customer_id',
                'team_id',
                'created_by',
                'modified_by',
                'modified_at'
            ]);
            
            $table->date('transaction_date')->after('id');
            $table->string('transaction_source')->after('transaction_date');
        });
    }
}

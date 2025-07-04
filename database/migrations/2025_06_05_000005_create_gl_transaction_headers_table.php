<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGlTransactionHeadersTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('fin_gl_transaction_headers', function (Blueprint $table) {
            addMetaData($table);

            $table->date('fiscal_date');
            $table->integer('gl_transaction_type');
            $table->integer('gl_transaction_number')->nullable();
            $table->string('transaction_description', 500);
            $table->boolean('is_posted')->default(false);
            $table->boolean('is_balanced')->default(false);

            // Multi-tenant support
            $table->foreignId('team_id')->constrained('teams')->onDelete('cascade');

            // Foreign key relationships using the ID field since we use addMetaData
            $table->foreignId('fiscal_period_id')->nullable()->constrained('fin_fiscal_periods');

            // Optional references to other modules
            $table->foreignId('customer_id')->nullable()->constrained('fin_customers');
            $table->integer('vendor_id')->nullable();

            // Indexes for performance
            $table->index(['team_id', 'fiscal_date']);
            $table->index(['team_id', 'gl_transaction_type']);
            $table->index(['team_id', 'is_posted']);
            $table->index(['team_id', 'fiscal_period_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('fin_gl_transaction_headers');
    }
}

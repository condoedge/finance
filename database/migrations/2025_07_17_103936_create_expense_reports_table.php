<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('fin_expense_reports');
        Schema::create('fin_expense_reports', function (Blueprint $table) {
            addMetaData($table);

            $table->string('expense_title', 255);

            $table->boolean('is_draft')->default(true);

            $table->foreignId('user_id')->nullable()->constrained()->onDelete('SET NULL');
            $table->foreignId('customer_id')->constrained('fin_customers')->onDelete('cascade');

            // Team id is redundant because the customer is already associated with a team.
            $table->foreignId('team_id')->constrained()->onDelete('cascade');

            $table->tinyInteger('expense_status')->default(1); // Default to PENDING status
            $table->string('expense_description', 1000)->nullable();

            // The sum of all expenses in this report. Calculated by db function triggered by integrity service.
            $table->decimal('amount_before_taxes', 19, config('kompo-finance.payment-related-decimal-scale', 2))->nullable()->default(0);
            $table->decimal('total_amount', 19, config('kompo-finance.payment-related-decimal-scale', 2))->nullable()->default(0);
        });

        Schema::create('fin_expenses', function (Blueprint $table) {
            addMetaData($table);

            $table->foreignId('expense_report_id')->constrained('fin_expense_reports')->onDelete('cascade');


            $table->tinyInteger('expense_type')->default(1); // Default to GENERAL expense type
            $table->decimal('expense_amount_before_taxes', 19, config('kompo-finance.payment-related-decimal-scale', 2));
            $table->decimal('total_expense_amount', 19, config('kompo-finance.payment-related-decimal-scale', 2));
            $table->date('expense_date')->nullable();
            $table->string('expense_description', 1000)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expense_reports');
    }
};

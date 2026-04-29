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
        Schema::create('fin_expense_report_types', function (Blueprint $table) {
            addMetaData($table);

            $table->json('name'); // Translatable name of the expense type

            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
        });

        Schema::table('fin_expenses', function (Blueprint $table) {
            $table->foreignId('expense_type_id')->nullable()->constrained('fin_expense_report_types')->nullOnDelete();

            $table->dropColumn('expense_type');
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

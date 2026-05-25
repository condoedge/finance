<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('fin_expense_reports', function (Blueprint $table) {
            // Holds the rejection reason (required on reject) or an optional
            // approval comment. Approver identity is the row's modified_by.
            $table->string('review_note', 1000)->nullable()->after('expense_description');
        });
    }

    public function down(): void
    {
        Schema::table('fin_expense_reports', function (Blueprint $table) {
            $table->dropColumn('review_note');
        });
    }
};

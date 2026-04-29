<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     *
     * Adds default handler functionality to account segments to enable
     * automatic value generation based on context (team, fiscal year, etc.)
     */
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->foreignId('default_revenue_account_id')->nullable()->constrained('fin_gl_accounts')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropForeignIdFor('default_revenue_account_id');
            $table->dropColumn('default_revenue_account_id');
        });
    }
};

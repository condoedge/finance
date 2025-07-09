<?php

use Condoedge\Finance\Models\PaymentInstallPeriodStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
        Schema::table('fin_invoices', function (Blueprint $table) {
            $table->timestamp('sent_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fin_invoices', function (Blueprint $table) {
            $table->dropColumn('sent_at');
        });
    }
};

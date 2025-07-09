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
        Schema::table('fin_payment_installment_periods', function (Blueprint $table) {
            $table->tinyInteger('status')->default(PaymentInstallPeriodStatusEnum::PENDING->value);
        });

        Schema::table('fin_invoice_statuses', function (Blueprint $table) {
            $table->string('code');
        });

        $functionsPath = __DIR__ . '/../sql/functions';

        // Load from files
        DB::unprepared(processDelimiters(file_get_contents($functionsPath . '/calculate_installment_period_status/calculate_installment_period_status_v0001.sql')));
        DB::unprepared(processDelimiters(file_get_contents($functionsPath . '/calculate_invoice_status/calculate_invoice_status_v0002.sql')));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fin_payment_installment_periods', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        DB::unprepared('DROP FUNCTION IF EXISTS calculate_installment_period_status');
        DB::unprepared('DROP FUNCTION IF EXISTS calculate_invoice_status');
    }
};

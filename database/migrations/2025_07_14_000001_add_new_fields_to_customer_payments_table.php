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
        Schema::table('fin_customer_payments', function (Blueprint $table) {
            $table->dropColumn('external_reference');

            $table->foreignId('payment_trace_id')->nullable()
                ->constrained('fin_payment_traces')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fin_invoices', function (Blueprint $table) {
            $table->dropColumn('complete_payment_managed_at');
            $table->dropColumn('partial_payment_managed_at');
        });
    }
};

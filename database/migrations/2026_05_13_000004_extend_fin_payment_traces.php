<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('fin_payment_traces', function (Blueprint $table) {
            $table->unsignedBigInteger('team_id')->nullable()->after('payable_type');
            $table->string('failure_reason_code', 64)->nullable()->after('payment_provider_code');
            $table->unsignedInteger('latency_ms')->nullable()->after('failure_reason_code');
            $table->unsignedSmallInteger('retry_count')->default(0)->after('latency_ms');

            $table->index(['payment_provider_code', 'team_id', 'created_at'], 'idx_health_query');
        });
    }

    public function down(): void
    {
        Schema::table('fin_payment_traces', function (Blueprint $table) {
            $table->dropIndex('idx_health_query');
            $table->dropColumn(['team_id', 'failure_reason_code', 'latency_ms', 'retry_count']);
        });
    }
};

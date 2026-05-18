<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('fin_provider_health_snapshots', function (Blueprint $table) {
            addMetaData($table);
            
            // Null = global health (provider-wide, not per-team).
            $table->unsignedBigInteger('team_id')->nullable();
            $table->string('provider_code', 32);
            $table->enum('status', ['healthy', 'degraded', 'down'])->default('healthy');
            $table->unsignedSmallInteger('consecutive_failures')->default(0);
            $table->timestamp('last_failure_at')->nullable();
            $table->timestamp('last_success_at')->nullable();

            $table->unique(['team_id', 'provider_code'], 'uq_team_provider_health');
            $table->index(['provider_code', 'status'], 'idx_provider_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fin_provider_health_snapshots');
    }
};

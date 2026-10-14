<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Payment providers, their health and settlement reports are project-wide: one chain in
 * config, env credentials. The per-team tables go, and so does team_id on the other two.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::dropIfExists('fin_team_payment_providers');
        Schema::dropIfExists('fin_provider_credentials');

        // Per-team rows were written but never read; health restarts from healthy.
        DB::table('fin_provider_health_snapshots')->delete();
        Schema::table('fin_provider_health_snapshots', function (Blueprint $table) {
            $table->dropUnique('uq_team_provider_health');
            $table->dropColumn('team_id');
            $table->unique('provider_code', 'uq_provider_health');
        });

        Schema::table('fin_settlement_files', function (Blueprint $table) {
            $table->dropUnique('uq_settlement_files_team_provider_filename');
            $table->dropUnique('uq_settlement_files_team_provider_sha');
            $table->dropIndex(['team_id']);
            $table->dropColumn('team_id');
            $table->unique(['provider_code', 'remote_filename'], 'uq_settlement_files_provider_filename');
            $table->unique(['provider_code', 'sha256'], 'uq_settlement_files_provider_sha');
        });
    }

    public function down(): void
    {
        Schema::table('fin_settlement_files', function (Blueprint $table) {
            $table->dropUnique('uq_settlement_files_provider_filename');
            $table->dropUnique('uq_settlement_files_provider_sha');
            $table->unsignedBigInteger('team_id')->nullable()->index();
            $table->unique(['team_id', 'provider_code', 'remote_filename'], 'uq_settlement_files_team_provider_filename');
            $table->unique(['team_id', 'provider_code', 'sha256'], 'uq_settlement_files_team_provider_sha');
        });

        Schema::table('fin_provider_health_snapshots', function (Blueprint $table) {
            $table->dropUnique('uq_provider_health');
            $table->unsignedBigInteger('team_id')->nullable();
            $table->unique(['team_id', 'provider_code'], 'uq_team_provider_health');
        });

        (include __DIR__ . '/2026_05_13_000001_create_fin_provider_credentials_table.php')->up();
        (include __DIR__ . '/2026_05_13_000002_create_fin_team_payment_providers_table.php')->up();
    }
};

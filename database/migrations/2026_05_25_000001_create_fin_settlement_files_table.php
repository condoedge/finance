<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('fin_settlement_files', function (Blueprint $table) {
            addMetaData($table);
            $table->unsignedBigInteger('team_id')->index();
            $table->string('provider_code', 32);
            $table->string('remote_filename', 255);
            $table->string('local_path', 512);
            $table->unsignedInteger('remote_size');
            $table->char('sha256', 64);
            $table->timestamp('fetched_at');
            $table->timestamp('imported_at')->nullable();
            $table->json('import_result_json')->nullable();
            $table->text('last_error')->nullable();

            $table->unique(
                ['team_id', 'provider_code', 'remote_filename'],
                'uq_settlement_files_team_provider_filename'
            );
            $table->unique(
                ['team_id', 'provider_code', 'sha256'],
                'uq_settlement_files_team_provider_sha'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fin_settlement_files');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('fin_provider_credentials', function (Blueprint $table) {
            addMetaData($table);
            $table->unsignedBigInteger('team_id')->nullable()->index();
            $table->string('provider_code', 32);
            // Laravel 'encrypted:array' cast (model side) handles encrypt/decrypt
            $table->text('credentials');
            $table->boolean('is_test')->default(false);
            $table->timestamp('last_rotated_at')->nullable();

            $table->index(['team_id', 'provider_code', 'is_test'], 'idx_team_provider_env');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fin_provider_credentials');
    }
};

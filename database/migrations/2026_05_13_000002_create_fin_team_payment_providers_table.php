<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('fin_team_payment_providers', function (Blueprint $table) {
            addMetaData($table);
            $table->unsignedBigInteger('team_id')->nullable();
            $table->unsignedSmallInteger('payment_method_id'); // matches PaymentMethodEnum
            $table->string('provider_code', 32);
            // Lower = higher priority. Primary = 1.
            $table->unsignedSmallInteger('priority')->default(1);
            $table->boolean('is_active')->default(true);
            // Per-row mode: single = surface failure, no retry.
            //               fallback = try next active provider for same method.
            $table->enum('mode', ['single', 'fallback'])->default('single');
            $table->unsignedBigInteger('credentials_id')->nullable();

            $table->index(['team_id', 'payment_method_id', 'priority'], 'idx_resolver_chain');
            $table->unique(['team_id', 'payment_method_id', 'provider_code'], 'uq_team_method_provider');

            $table->foreign('credentials_id')
                ->references('id')
                ->on('fin_provider_credentials')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fin_team_payment_providers');
    }
};

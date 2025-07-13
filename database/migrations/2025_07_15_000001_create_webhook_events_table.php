<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('fin_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider_code', 50)->index();
            $table->string('webhook_id', 255);
            $table->json('payload');
            $table->timestamp('processed_at');
            $table->timestamps();
            
            // Unique constraint to prevent duplicate processing
            $table->unique(['provider_code', 'webhook_id']);
            
            // Index for cleanup queries
            $table->index('processed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('fin_webhook_events');
    }
};

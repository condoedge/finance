<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('fin_default_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('team_id');
            $table->string('setting_name', 100); // e.g., 'default_revenue_account', 'default_expense_account'
            $table->string('account_id', 50);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            // Foreign keys
            $table->foreign('team_id')->references('id')->on('teams')->onDelete('cascade');
            $table->foreign('account_id')->references('account_id')->on('fin_gl_accounts')->onDelete('cascade');
            
            // Unique constraint to prevent duplicate settings per team
            $table->unique(['team_id', 'setting_name']);
            
            // Indexes
            $table->index(['team_id', 'is_active']);
            $table->index('setting_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fin_default_accounts');
    }
};

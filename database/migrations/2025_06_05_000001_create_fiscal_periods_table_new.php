<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFiscalPeriodsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('fin_fiscal_periods', function (Blueprint $table) {
            addMetaData($table);
            
            $table->string('period_id', 10)->unique(); // e.g., 'per01', 'per02'
            $table->unsignedSmallInteger('fiscal_year'); // e.g., 2025
            $table->unsignedTinyInteger('period_number'); // 1-12
            $table->date('start_date');
            $table->date('end_date');
            
            // Multi-tenant support
            $table->foreignId('team_id')->constrained('teams')->onDelete('cascade');
            
            // Module-specific open/closed status
            $table->boolean('is_open_gl')->default(true);
            $table->boolean('is_open_bnk')->default(true);
            $table->boolean('is_open_rm')->default(true);
            $table->boolean('is_open_pm')->default(true);
            
            // Indexes for performance
            $table->index(['team_id', 'fiscal_year', 'period_number']);
            $table->index('start_date');
            $table->index('end_date');
            
            // Ensure no overlapping periods per team
            $table->unique(['team_id', 'fiscal_year', 'period_number']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('fin_fiscal_periods');
    }
}

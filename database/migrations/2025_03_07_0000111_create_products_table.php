<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('fin_products');
        
        Schema::create('fin_products', function (Blueprint $table) {
            addMetaData($table);

            $table->tinyInteger('product_type');
            $table->string('product_name')->nullable();
            $table->string('product_description')->nullable();
            $table->decimal('product_cost', 19, 5);
            $table->decimal('product_cost_total', 19, 5)->nullable();
            $table->foreignId('team_id')->nullable()->constrained();
            $table->foreignId('default_revenue_account_id')->constrained('fin_gl_accounts');

            $table->json('taxes_ids')
                ->nullable();

            $table->nullableMorphs('productable');
        });

        Schema::table('fin_products', function (Blueprint $table) {
            $table->foreignId('product_template_id')->nullable()->constrained('fin_products');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fin_products');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     *
     * Adds default handler functionality to account segments to enable
     * automatic value generation based on context (team, fiscal year, etc.)
     */
    public function up(): void
    {
        Schema::table('fin_products', function (Blueprint $table) {
            // Add product cost always positive column
            $table->decimal('product_cost_abs', 19, 5)
                ->nullable();

            $table->decimal('product_taxes_amount', 19, 5)
                ->nullable()
                ->after('product_cost_abs')
                ->comment('Total taxes amount for the product');
        });

        Schema::table('fin_products', function (Blueprint $table) {
            $table->decimal('product_cost_total')->storedAs('product_cost + product_taxes_amount')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fin_products', function (Blueprint $table) {
            // Remove product cost sign sensitive column
            $table->dropColumn('product_cost_abs');
            $table->dropColumn('product_taxes_amount');
        });
    }
};

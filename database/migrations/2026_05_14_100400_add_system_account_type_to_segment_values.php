<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('fin_segment_values', function (Blueprint $table) {
            $table->string('system_account_type', 20)->nullable()->after('account_type');
            $table->unique('system_account_type', 'fin_segment_values_system_account_type_unique');
        });
    }

    public function down(): void
    {
        Schema::table('fin_segment_values', function (Blueprint $table) {
            $table->dropUnique('fin_segment_values_system_account_type_unique');
            $table->dropColumn('system_account_type');
        });
    }
};

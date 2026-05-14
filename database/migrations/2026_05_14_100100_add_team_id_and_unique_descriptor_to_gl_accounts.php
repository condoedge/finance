<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('fin_gl_accounts', function (Blueprint $table) {
            $table->foreignId('team_id')->nullable()->after('id')
                ->constrained('teams')->nullOnDelete();
            $table->unique('account_segments_descriptor', 'fin_gl_accounts_descriptor_unique');
        });
    }

    public function down(): void
    {
        Schema::table('fin_gl_accounts', function (Blueprint $table) {
            $table->dropUnique('fin_gl_accounts_descriptor_unique');
            $table->dropConstrainedForeignId('team_id');
        });
    }
};

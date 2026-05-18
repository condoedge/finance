<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fin_taxes', function (Blueprint $table) {
            $table->renameColumn('valide_from', 'valid_from');
            $table->renameColumn('valide_to', 'valid_to');
        });
    }

    public function down(): void
    {
        Schema::table('fin_taxes', function (Blueprint $table) {
            $table->renameColumn('valid_from', 'valide_from');
            $table->renameColumn('valid_to', 'valide_to');
        });
    }
};

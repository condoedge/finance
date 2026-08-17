<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A credit note that corrects a specific invoice points back at it, so both pages
 * can cross-link and reporting can tell a correction from a standalone credit.
 */
return new class () extends Migration {
    public function up()
    {
        // fin_invoices.invoice_date carries a legacy '0000-00-00 00:00:00' default, so any
        // rebuild of this table is refused under NO_ZERO_DATE. Relaxed for this ALTER only;
        // no data is touched.
        $mode = DB::selectOne('SELECT @@session.sql_mode as mode')->mode;
        DB::statement("SET SESSION sql_mode = REPLACE(REPLACE(@@session.sql_mode, 'NO_ZERO_DATE', ''), 'NO_ZERO_IN_DATE', '')");

        try {
            Schema::table('fin_invoices', function (Blueprint $table) {
                $table->foreignId('credited_invoice_id')->nullable()->after('invoice_type_id')
                    ->constrained('fin_invoices');
            });
        } finally {
            DB::statement('SET SESSION sql_mode = ?', [$mode]);
        }
    }

    public function down()
    {
        Schema::table('fin_invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('credited_invoice_id');
        });
    }
};

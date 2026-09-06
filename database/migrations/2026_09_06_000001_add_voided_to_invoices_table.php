<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Voiding is flagged, not stored as a status: invoice_status_id is calculated by
 * calculate_invoice_status() and rewritten by IntegrityChecker on every touch, so a
 * status written from PHP would not survive. Nothing recalculates these two columns.
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
                $table->timestamp('voided_at')->nullable();
                $table->foreignId('voided_by')->nullable()->constrained('users');
            });
        } finally {
            DB::statement('SET SESSION sql_mode = ?', [$mode]);
        }
    }

    public function down()
    {
        Schema::table('fin_invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('voided_by');
            $table->dropColumn('voided_at');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Invoices created before the service stamped team_id all carry NULL. The insert
     * trigger snapshots the customer's team into fin_historical_customers, so that
     * snapshot is the value the row should have had at creation time.
     */
    public function up(): void
    {
        DB::statement(<<<'SQL'
            UPDATE fin_invoices
            SET team_id = (
                SELECT team_id FROM fin_historical_customers
                WHERE fin_historical_customers.id = fin_invoices.historical_customer_id
            )
            WHERE team_id IS NULL
        SQL);
    }

    public function down(): void
    {
        // Data backfill — nothing to revert.
    }
};

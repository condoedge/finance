<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Derive team_id from each account's team-segment assignment.
        // Rows whose team segment was truncated by the pre-fix handler cannot
        // be recovered — they keep team_id NULL and should be reviewed.
        DB::statement("
            UPDATE fin_gl_accounts ga
            JOIN fin_account_segment_assignments asa ON asa.account_id = ga.id
            JOIN fin_segment_values sv ON sv.id = asa.segment_value_id
            JOIN fin_account_segments seg ON seg.id = sv.segment_definition_id
            SET ga.team_id = CAST(sv.segment_value AS UNSIGNED)
            WHERE seg.default_handler = 'team'
              AND ga.team_id IS NULL
        ");
    }

    public function down(): void
    {
        // Backfill — nothing to reverse.
    }
};

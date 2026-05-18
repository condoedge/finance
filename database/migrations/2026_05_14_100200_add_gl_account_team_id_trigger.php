<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_gl_account_team_id_after_assignment_insert');
        DB::unprepared("
            CREATE TRIGGER trg_gl_account_team_id_after_assignment_insert
            AFTER INSERT ON fin_account_segment_assignments
            FOR EACH ROW
            BEGIN
                DECLARE v_team_value VARCHAR(20);
                SELECT sv.segment_value INTO v_team_value
                FROM fin_segment_values sv
                JOIN fin_account_segments seg ON seg.id = sv.segment_definition_id
                WHERE sv.id = NEW.segment_value_id
                  AND seg.default_handler = 'team'
                LIMIT 1;

                IF v_team_value IS NOT NULL THEN
                    UPDATE fin_gl_accounts
                    SET team_id = CAST(v_team_value AS UNSIGNED)
                    WHERE id = NEW.account_id;
                END IF;
            END
        ");
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_gl_account_team_id_after_assignment_insert');
    }
};

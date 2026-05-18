<?php

namespace Tests\Unit;

use Condoedge\Finance\Enums\SegmentDefaultHandlerEnum;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EnsureTeamSegmentWidthMigrationTest extends TestCase
{
    public function test_narrow_team_segment_is_widened_to_7()
    {
        DB::table('fin_account_segment_assignments')->delete();
        DB::table('fin_gl_accounts')->delete();
        DB::table('fin_segment_values')->delete();
        DB::table('fin_account_segments')->delete();
        DB::table('fin_account_segments')->insert([
            'segment_description' => 'trest',
            'segment_position' => 1,
            'segment_length' => 3,
            'default_handler' => SegmentDefaultHandlerEnum::TEAM->value,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $migration = require __DIR__ . '/../../database/migrations/2026_05_14_100000_ensure_team_segment_width.php';
        $migration->up();

        $segment = DB::table('fin_account_segments')
            ->where('default_handler', SegmentDefaultHandlerEnum::TEAM->value)->first();
        $this->assertSame(7, (int) $segment->segment_length);
    }

    public function test_missing_team_segment_is_created_at_width_7()
    {
        DB::table('fin_account_segment_assignments')->delete();
        DB::table('fin_gl_accounts')->delete();
        DB::table('fin_segment_values')->delete();
        DB::table('fin_account_segments')->delete();

        $migration = require __DIR__ . '/../../database/migrations/2026_05_14_100000_ensure_team_segment_width.php';
        $migration->up();

        $segment = DB::table('fin_account_segments')
            ->where('default_handler', SegmentDefaultHandlerEnum::TEAM->value)->first();
        $this->assertNotNull($segment);
        $this->assertSame(7, (int) $segment->segment_length);
    }
}

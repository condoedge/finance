<?php

namespace Tests\Unit;

use Condoedge\Finance\Enums\SegmentDefaultHandlerEnum;
use Condoedge\Finance\Models\AccountSegment;
use Condoedge\Finance\Services\AccountSegmentService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CanonicalSegmentStructureTest extends TestCase
{
    public function test_create_default_segments_builds_team_then_natural_account()
    {
        DB::table('fin_account_segment_assignments')->delete();
        DB::table('fin_gl_accounts')->delete();
        DB::table('fin_segment_values')->delete();
        DB::table('fin_account_segments')->delete();

        app(AccountSegmentService::class)->createDefaultSegments();

        $segments = AccountSegment::orderBy('segment_position')->get();
        $this->assertCount(2, $segments);

        $this->assertSame(1, $segments[0]->segment_position);
        $this->assertSame(7, $segments[0]->segment_length);
        $this->assertSame(SegmentDefaultHandlerEnum::TEAM->value, $segments[0]->default_handler);

        $this->assertSame(2, $segments[1]->segment_position);
        $this->assertSame(4, $segments[1]->segment_length);
        $this->assertSame(SegmentDefaultHandlerEnum::MANUAL->value, $segments[1]->default_handler);
    }
}

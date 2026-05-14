<?php

namespace Tests\Unit;

use Condoedge\Finance\Enums\SegmentDefaultHandlerEnum;
use Condoedge\Finance\Exceptions\SegmentValueOverflowException;
use Condoedge\Finance\Models\AccountSegment;
use Condoedge\Finance\Models\Dto\Gl\CreateOrUpdateSegmentDto;
use Condoedge\Finance\Services\AccountSegmentService;
use Condoedge\Finance\Services\SegmentDefaultHandlerService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SegmentDefaultHandlerOverflowTest extends TestCase
{
    protected SegmentDefaultHandlerService $handler;

    public function setUp(): void
    {
        parent::setUp();
        DB::table('fin_account_segment_assignments')->delete();
        DB::table('fin_gl_accounts')->delete();
        DB::table('fin_segment_values')->delete();
        DB::table('fin_account_segments')->delete();
        $this->handler = app(SegmentDefaultHandlerService::class);
    }

    protected function teamSegment(int $length): AccountSegment
    {
        return app(AccountSegmentService::class)->createOrUpdateSegment(new CreateOrUpdateSegmentDto([
            'segment_description' => 'Team',
            'segment_position' => 1,
            'segment_length' => $length,
            'default_handler' => SegmentDefaultHandlerEnum::TEAM,
        ]));
    }

    public function test_team_id_shorter_than_length_is_left_padded()
    {
        $segment = $this->teamSegment(7);
        $value = $this->handler->resolveDefaultValue($segment, ['team_id' => 47]);
        $this->assertSame('0000047', $value->segment_value);
    }

    public function test_team_id_exactly_length_passes_through()
    {
        $segment = $this->teamSegment(7);
        $value = $this->handler->resolveDefaultValue($segment, ['team_id' => 1234567]);
        $this->assertSame('1234567', $value->segment_value);
    }

    public function test_team_id_longer_than_length_throws_and_does_not_truncate()
    {
        $segment = $this->teamSegment(3);
        $this->expectException(SegmentValueOverflowException::class);
        $this->handler->resolveDefaultValue($segment, ['team_id' => 16370]);
    }
}

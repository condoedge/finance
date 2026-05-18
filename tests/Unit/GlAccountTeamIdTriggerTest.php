<?php

namespace Tests\Unit;

use Condoedge\Finance\Enums\SegmentDefaultHandlerEnum;
use Condoedge\Finance\Models\AccountSegment;
use Condoedge\Finance\Models\Dto\Gl\CreateOrUpdateSegmentDto;
use Condoedge\Finance\Models\Dto\Gl\CreateSegmentValueDto;
use Condoedge\Finance\Services\AccountSegmentService;
use Illuminate\Support\Facades\DB;
use Kompo\Auth\Database\Factories\UserFactory;
use Tests\TestCase;

class GlAccountTeamIdTriggerTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        $this->actingAs(UserFactory::new()->create()->first());
    }

    public function test_trigger_populates_team_id_when_assignment_inserted_directly()
    {
        DB::table('fin_account_segment_assignments')->delete();
        DB::table('fin_gl_accounts')->delete();
        DB::table('fin_segment_values')->delete();
        DB::table('fin_account_segments')->delete();

        $service = app(AccountSegmentService::class);

        // A team-handler segment + a team segment value '0000047'.
        $service->createOrUpdateSegment(new CreateOrUpdateSegmentDto([
            'segment_description' => 'Team',
            'segment_position' => 1,
            'segment_length' => 7,
            'default_handler' => SegmentDefaultHandlerEnum::TEAM,
        ]));
        $teamSegment = AccountSegment::where('default_handler', SegmentDefaultHandlerEnum::TEAM->value)->first();
        $teamValue = $service->createSegmentValue(new CreateSegmentValueDto([
            'segment_definition_id' => $teamSegment->id,
            'segment_value' => '0000047',
            'segment_description' => 'Team 47',
            'is_active' => true,
        ]));

        // A bare gl_account row with no team_id, no descriptor.
        $accountId = DB::table('fin_gl_accounts')->insertGetId([
            'account_segments_descriptor' => null,
            'is_active' => true,
            'allow_manual_entry' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insert the assignment directly, bypassing createAccount().
        DB::table('fin_account_segment_assignments')->insert([
            'account_id' => $accountId,
            'segment_value_id' => $teamValue->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame(47, (int) DB::table('fin_gl_accounts')->where('id', $accountId)->value('team_id'));
    }
}

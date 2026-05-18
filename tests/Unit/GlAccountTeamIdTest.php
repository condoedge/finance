<?php

namespace Tests\Unit;

use Condoedge\Finance\Models\AccountSegment;
use Condoedge\Finance\Models\Dto\Gl\CreateAccountDto;
use Condoedge\Finance\Models\Dto\Gl\CreateSegmentValueDto;
use Condoedge\Finance\Models\GlAccount;
use Condoedge\Finance\Services\AccountSegmentService;
use Illuminate\Support\Facades\DB;
use Kompo\Auth\Database\Factories\UserFactory;
use Tests\TestCase;

class GlAccountTeamIdTest extends TestCase
{
    protected AccountSegmentService $service;

    public function setUp(): void
    {
        parent::setUp();
        $this->actingAs(UserFactory::new()->create()->first());
        DB::table('fin_account_segment_assignments')->delete();
        DB::table('fin_gl_accounts')->delete();
        DB::table('fin_segment_values')->delete();
        DB::table('fin_account_segments')->delete();
        $this->service = app(AccountSegmentService::class);
        $this->service->createDefaultSegments(); // Team(pos1,len7,team) + Account(pos2,len4,manual)
    }

    protected function naturalAccountValue(string $code = '4000'): \Condoedge\Finance\Models\SegmentValue
    {
        return $this->service->createSegmentValue(new CreateSegmentValueDto([
            'segment_definition_id' => AccountSegment::where('segment_position', 2)->first()->id,
            'segment_value' => $code,
            'segment_description' => 'Cash',
            'is_active' => true,
        ]));
    }

    public function test_create_account_stamps_team_id_from_team_segment()
    {
        $teamId  = currentTeamId();
        $natural = $this->naturalAccountValue();

        $account = $this->service->createAccount(new CreateAccountDto([
            'segment_value_ids' => [$natural->id],
            'is_active' => true,
            'allow_manual_entry' => true,
            'apply_defaults' => true,
        ]));

        $this->assertSame($teamId, $account->team_id);
        $this->assertNotNull($account->account_segments_descriptor);
        $this->assertNotSame('TEMP', $account->account_segments_descriptor);
    }

    public function test_create_account_for_existing_team_natural_pair_returns_same_row()
    {
        $natural = $this->naturalAccountValue();

        $dto = fn () => new CreateAccountDto([
            'segment_value_ids' => [$natural->id],
            'is_active' => true,
            'allow_manual_entry' => true,
            'apply_defaults' => true,
        ]);

        $a = $this->service->createAccount($dto());
        $b = $this->service->createAccount($dto());

        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, GlAccount::count());
    }
}

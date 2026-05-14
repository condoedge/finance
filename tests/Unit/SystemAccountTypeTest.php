<?php

namespace Tests\Unit;

use Condoedge\Finance\Enums\SegmentDefaultHandlerEnum;
use Condoedge\Finance\Enums\SystemAccountTypeEnum;
use Condoedge\Finance\Models\AccountSegment;
use Condoedge\Finance\Models\Dto\Gl\CreateOrUpdateSegmentDto;
use Condoedge\Finance\Models\SegmentValue;
use Condoedge\Finance\Services\AccountSegmentService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SystemAccountTypeTest extends TestCase
{
    protected AccountSegment $segment;

    public function setUp(): void
    {
        parent::setUp();
        DB::table('fin_account_segment_assignments')->delete();
        DB::table('fin_gl_accounts')->delete();
        DB::table('fin_segment_values')->delete();
        DB::table('fin_account_segments')->delete();

        $this->segment = app(AccountSegmentService::class)->createOrUpdateSegment(new CreateOrUpdateSegmentDto([
            'segment_description' => 'Account',
            'segment_position' => 1,
            'segment_length' => 4,
            'default_handler' => SegmentDefaultHandlerEnum::MANUAL,
        ]));
    }

    protected function makeValue(string $code): SegmentValue
    {
        $v = new SegmentValue();
        $v->segment_definition_id = $this->segment->id;
        $v->segment_value = $code;
        $v->segment_description = 'desc ' . $code;
        $v->is_active = true;
        $v->save();
        return $v;
    }

    public function test_system_account_type_casts_to_enum()
    {
        $v = $this->makeValue('1000');
        $v->system_account_type = SystemAccountTypeEnum::CASH;
        $v->save();

        $this->assertSame(SystemAccountTypeEnum::CASH, $v->fresh()->system_account_type);
    }

    public function test_only_one_segment_value_can_be_cash()
    {
        $first = $this->makeValue('1000');
        $first->system_account_type = SystemAccountTypeEnum::CASH;
        $first->save();

        $second = $this->makeValue('1001');
        $this->expectException(UniqueConstraintViolationException::class);
        $second->system_account_type = SystemAccountTypeEnum::CASH;
        $second->save();
    }

    public function test_multiple_values_can_have_null_system_account_type()
    {
        $this->makeValue('2000');
        $this->makeValue('2001');
        $this->expectNotToPerformAssertions();
    }
}

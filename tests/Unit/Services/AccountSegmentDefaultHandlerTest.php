<?php

namespace Condoedge\Finance\Tests\Unit\Services;

use Condoedge\Finance\Enums\SegmentDefaultHandlerEnum;
use Condoedge\Finance\Facades\AccountSegmentService;
use Condoedge\Finance\Models\AccountSegment;
use Condoedge\Finance\Models\Dto\Gl\CreateOrUpdateSegmentDto;
use Condoedge\Finance\Models\Dto\Gl\CreateSegmentValueDto;
use Condoedge\Finance\Models\GlAccount;
use Condoedge\Finance\Models\Team;
use Condoedge\Finance\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AccountSegmentDefaultHandlerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Set up test team structure
        $this->parentTeam = Team::factory()->create(['id' => 10, 'name' => 'Parent Company']);
        $this->team = Team::factory()->create([
            'id' => 1,
            'parent_id' => $this->parentTeam->id,
            'name' => 'Test Team'
        ]);
        
        $this->actingAs($this->createUserWithTeam($this->team));
    }

    public function test_it_validates_last_segment_must_contain_account_in_description()
    {
        // Create first segments
        AccountSegmentService::createOrUpdateSegment(new CreateOrUpdateSegmentDto([
            'segment_description' => 'Company',
            'segment_position' => 1,
            'segment_length' => 2,
        ]));

        // Try to create last segment without 'account' in description
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('last-segment-must-contain-account-in-description');

        AccountSegmentService::createOrUpdateSegment(new CreateOrUpdateSegmentDto([
            'segment_description' => 'Cost Center', // Missing 'account'
            'segment_position' => 2,
            'segment_length' => 4,
        ]));
    }

    public function test_it_creates_segment_with_default_handler()
    {
        $segment = AccountSegmentService::createOrUpdateSegment(new CreateOrUpdateSegmentDto([
            'segment_description' => 'Team',
            'segment_position' => 1,
            'segment_length' => 2,
            'default_handler' => SegmentDefaultHandlerEnum::TEAM->value,
        ]));

        $this->assertEquals(SegmentDefaultHandlerEnum::TEAM->value, $segment->default_handler);
        $this->assertTrue($segment->hasDefaultHandler());
        $this->assertFalse($segment->requiresHandlerConfig());
    }

    public function test_it_creates_segment_with_sequence_handler_and_config()
    {
        $config = [
            'prefix' => 'D',
            'sequence_scope' => 'team',
            'start_value' => 100,
        ];

        $segment = AccountSegmentService::createOrUpdateSegment(new CreateOrUpdateSegmentDto([
            'segment_description' => 'Department',
            'segment_position' => 1,
            'segment_length' => 4,
            'default_handler' => SegmentDefaultHandlerEnum::SEQUENCE->value,
            'default_handler_config' => $config,
        ]));

        $this->assertEquals(SegmentDefaultHandlerEnum::SEQUENCE->value, $segment->default_handler);
        $this->assertEquals($config, $segment->default_handler_config);
        $this->assertTrue($segment->requiresHandlerConfig());
    }

    public function test_it_creates_account_from_last_segment_only()
    {
        // Set up segments with handlers
        AccountSegmentService::createOrUpdateSegment(new CreateOrUpdateSegmentDto([
            'segment_description' => 'Parent Team',
            'segment_position' => 1,
            'segment_length' => 2,
            'default_handler' => SegmentDefaultHandlerEnum::PARENT_TEAM->value,
        ]));

        AccountSegmentService::createOrUpdateSegment(new CreateOrUpdateSegmentDto([
            'segment_description' => 'Team',
            'segment_position' => 2,
            'segment_length' => 2,
            'default_handler' => SegmentDefaultHandlerEnum::TEAM->value,
        ]));

        $accountSegment = AccountSegmentService::createOrUpdateSegment(new CreateOrUpdateSegmentDto([
            'segment_description' => 'Natural Account',
            'segment_position' => 3,
            'segment_length' => 4,
            'default_handler' => null, // Manual
        ]));

        // Create account value
        $accountValue = AccountSegmentService::createSegmentValue(new CreateSegmentValueDto([
            'segment_definition_id' => $accountSegment->id,
            'segment_value' => '1000',
            'segment_description' => 'Cash',
            'is_active' => true,
        ]));

        // Check we can create from last segment
        $this->assertTrue(AccountSegmentService::canCreateAccountFromLastSegmentOnly());

        // Create account
        $account = AccountSegmentService::createAccountFromLastSegment(
            $accountValue->id,
            [
                'account_description' => 'Test Cash Account',
                'account_type' => 'asset',
                'team_id' => $this->team->id,
                'is_active' => true,
                'allow_manual_entry' => true,
            ]
        );

        $this->assertInstanceOf(GlAccount::class, $account);
        $this->assertStringContainsString('10', $account->account_id); // Parent team ID
        $this->assertStringContainsString('01', $account->account_id); // Team ID
        $this->assertStringContainsString('1000', $account->account_id); // Account value
    }

    public function test_it_fails_to_create_from_last_segment_when_handlers_missing()
    {
        // Create segments without handlers
        AccountSegmentService::createOrUpdateSegment(new CreateOrUpdateSegmentDto([
            'segment_description' => 'Company',
            'segment_position' => 1,
            'segment_length' => 2,
            'default_handler' => null, // No handler
        ]));

        $accountSegment = AccountSegmentService::createOrUpdateSegment(new CreateOrUpdateSegmentDto([
            'segment_description' => 'Natural Account',
            'segment_position' => 2,
            'segment_length' => 4,
        ]));

        // Check we cannot create from last segment
        $this->assertFalse(AccountSegmentService::canCreateAccountFromLastSegmentOnly());

        // Create account value
        $accountValue = AccountSegmentService::createSegmentValue(new CreateSegmentValueDto([
            'segment_definition_id' => $accountSegment->id,
            'segment_value' => '1000',
            'segment_description' => 'Cash',
            'is_active' => true,
        ]));

        // Try to create account - should fail
        $this->expectException(\InvalidArgumentException::class);
        
        AccountSegmentService::createAccountFromLastSegment(
            $accountValue->id,
            ['team_id' => $this->team->id]
        );
    }

    public function test_it_creates_account_with_fixed_value_handler()
    {
        // Create segment with fixed value
        AccountSegmentService::createOrUpdateSegment(new CreateOrUpdateSegmentDto([
            'segment_description' => 'Company',
            'segment_position' => 1,
            'segment_length' => 2,
            'default_handler' => SegmentDefaultHandlerEnum::FIXED_VALUE->value,
            'default_handler_config' => ['value' => '99'],
        ]));

        $accountSegment = AccountSegmentService::createOrUpdateSegment(new CreateOrUpdateSegmentDto([
            'segment_description' => 'Natural Account',
            'segment_position' => 2,
            'segment_length' => 4,
        ]));

        $accountValue = AccountSegmentService::createSegmentValue(new CreateSegmentValueDto([
            'segment_definition_id' => $accountSegment->id,
            'segment_value' => '2000',
            'segment_description' => 'Revenue',
            'is_active' => true,
        ]));

        // Create account using defaults
        $account = AccountSegmentService::createAccountWithDefaults(
            [$accountValue->id],
            [
                'account_description' => 'Fixed Company Revenue',
                'account_type' => 'revenue',
                'team_id' => $this->team->id,
                'is_active' => true,
            ]
        );

        $this->assertEquals('99-2000', $account->account_id);
    }

    public function test_it_validates_last_segment_on_update()
    {
        // Create segments
        $segment1 = AccountSegmentService::createOrUpdateSegment(new CreateOrUpdateSegmentDto([
            'segment_description' => 'Company',
            'segment_position' => 1,
            'segment_length' => 2,
        ]));

        $segment2 = AccountSegmentService::createOrUpdateSegment(new CreateOrUpdateSegmentDto([
            'segment_description' => 'Natural Account',
            'segment_position' => 2,
            'segment_length' => 4,
        ]));

        // Try to update last segment to remove 'account' from description
        $this->expectException(\InvalidArgumentException::class);

        AccountSegmentService::createOrUpdateSegment(new CreateOrUpdateSegmentDto([
            'id' => $segment2->id,
            'segment_description' => 'Cost Center', // Removing 'account'
            'segment_position' => 2,
            'segment_length' => 4,
        ]));
    }

    public function test_it_gets_last_segment()
    {
        AccountSegmentService::createOrUpdateSegment(new CreateOrUpdateSegmentDto([
            'segment_description' => 'Team',
            'segment_position' => 1,
            'segment_length' => 2,
        ]));

        $lastSegment = AccountSegmentService::createOrUpdateSegment(new CreateOrUpdateSegmentDto([
            'segment_description' => 'Natural Account',
            'segment_position' => 2,
            'segment_length' => 4,
        ]));

        $retrieved = AccountSegmentService::getLastSegment();
        
        $this->assertEquals($lastSegment->id, $retrieved->id);
        $this->assertEquals(2, $retrieved->segment_position);
    }
}

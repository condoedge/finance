<?php

namespace Tests\Unit;

use Condoedge\Finance\Enums\SegmentDefaultHandlerEnum;
use Condoedge\Finance\Models\AccountSegment;
use Condoedge\Finance\Models\Dto\Gl\CreateAccountDto;
use Condoedge\Finance\Models\Dto\Gl\CreateOrUpdateSegmentDto;
use Condoedge\Finance\Models\Dto\Gl\CreateSegmentValueDto;
use Condoedge\Finance\Models\SegmentValue;
use Condoedge\Finance\Services\AccountSegmentService;
use Exception;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Kompo\Auth\Database\Factories\UserFactory;
use Tests\TestCase;

class AccountSegmentSystemTest extends TestCase
{
    use WithFaker;

    protected AccountSegmentService $segmentService;

    public function setUp(): void
    {
        parent::setUp();

        /** @var \Kompo\Auth\Models\User $user */
        $user = UserFactory::new()->create()->first();
        if (!$user) {
            throw new Exception('Unknown error creating user');
        }
        $this->actingAs($user);

        $this->segmentService = app(AccountSegmentService::class);

        // Setup default segment structure for tests
        $this->setupTestSegmentStructure();
    }

    /**
     * Test that segment structure can be created and retrieved correctly
     */
    public function test_it_creates_segment_structure()
    {
        // Structure should already be created in setUp
        $structure = $this->segmentService->getSegmentStructure();

        $this->assertCount(3, $structure);

        // Verify first segment
        $this->assertEquals('Parent Team', $structure[0]->segment_description);
        $this->assertEquals(1, $structure[0]->segment_position);
        $this->assertEquals(2, $structure[0]->segment_length);

        // Verify second segment
        $this->assertEquals('Team', $structure[1]->segment_description);
        $this->assertEquals(2, $structure[1]->segment_position);
        $this->assertEquals(2, $structure[1]->segment_length);

        // Verify third segment
        $this->assertEquals('Natural Account', $structure[2]->segment_description);
        $this->assertEquals(3, $structure[2]->segment_position);
        $this->assertEquals(4, $structure[2]->segment_length);
    }

    /**
     * Test that segment values can be created with proper validation
     */
    public function test_it_creates_segment_values_with_validation()
    {
        $segment = AccountSegment::where('segment_position', 1)->first();

        // Create valid segment value
        $segmentValue = $this->segmentService->createSegmentValue(new CreateSegmentValueDto([
            'segment_definition_id' => $segment->id,
            'segment_value' => '10',
            'segment_description' => 'Headquarters',
            'is_active' => true,
        ]));

        $this->assertDatabaseHas('fin_segment_values', [
            'id' => $segmentValue->id,
            'segment_definition_id' => $segment->id,
            'segment_value' => '10',
            'segment_description' => 'Headquarters',
            'is_active' => 1,
        ]);

        // Test getting error when segment value is too short
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage(__('error-value-length-mismatch'));

        $this->segmentService->createSegmentValue(new CreateSegmentValueDto([
            'segment_definition_id' => $segment->id,
            'segment_value' => '5',
            'segment_description' => 'Branch Office',
            'is_active' => true,
        ]));
    }

    /**
     * Test that it generates correct account_segments_descriptor from segment assignments
     */
    public function test_it_generates_correct_account_segments_descriptor()
    {
        $segmentValues = $this->createTestSegmentValues();

        // Create account using segment values
        $account = $this->segmentService->createAccount(new CreateAccountDto([
            'segment_value_ids' => array_column($segmentValues, 'id'),
            'is_active' => true,
            'allow_manual_entry' => true,
        ]));

        // Verify account was created
        $this->assertNotNull($account);

        // Force refresh to get trigger-calculated values
        $account->refresh();

        // Verify the account_segments_descriptor was built correctly
        $this->assertEquals('10-03-4000', $account->account_segments_descriptor);
    }

    /**
     * Test that duplicate accounts are prevented
     */
    public function test_it_prevents_duplicate_accounts()
    {
        $segmentValues = $this->createTestSegmentValues();
        $valueIds = array_column($segmentValues, 'id');

        // Create first account
        $account1 = $this->segmentService->createAccount(new CreateAccountDto([
            'segment_value_ids' => $valueIds,
            'is_active' => true,
            'allow_manual_entry' => true,
            'account_type' => 'asset',
            'team_id' => 1,
        ]));

        // Try to create duplicate account with same segments
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(__('error-account-already-exists'));

        $this->segmentService->createAccount(new CreateAccountDto([
            'segment_value_ids' => $valueIds,
            'is_active' => true,
            'allow_manual_entry' => true,
            'account_type' => 'asset',
            'team_id' => 1,
        ]));
    }

    /**
     * Test that segment lengths are validated
     */
    public function test_it_validates_segment_lengths()
    {
        $segment = AccountSegment::where('segment_position', 3)->where('segment_length', 4)->first();

        // Try to create value that's too long
        $this->expectException(ValidationException::class);

        $this->segmentService->createSegmentValue(new CreateSegmentValueDto([
            'segment_definition_id' => $segment->id,
            'segment_value' => '12345', // 5 chars but segment expects 4
            'segment_description' => 'Invalid Length',
            'is_active' => true,
        ]));
    }

    /**
     * Test that default handlers work correctly
     */
    public function test_it_handles_default_handlers()
    {
        // Update first segment to have parent_team_based handler
        $parentSegment = AccountSegment::where('segment_position', 1)->first();
        $parentSegment->default_handler = SegmentDefaultHandlerEnum::PARENT_TEAM;
        $parentSegment->save();

        // Create segment values
        $teamSegment = AccountSegment::where('segment_position', 2)->first();
        $teamSegment->default_handler = SegmentDefaultHandlerEnum::TEAM;
        $teamSegment->save();

        $accountSegment = AccountSegment::where('segment_position', 3)->first();

        // Create account value
        $accountValue = $this->segmentService->createSegmentValue(new CreateSegmentValueDto([
            'segment_definition_id' => $accountSegment->id,
            'segment_value' => '5000',
            'segment_description' => 'Revenue',
            'is_active' => true,
        ]));

        // Create account using only last segment (should apply defaults)
        $account = $this->segmentService->createAccountFromLastValue($accountValue->id);

        $account->refresh();

        $firstSegmentValue = str_pad(currentTeam()->parent_team_id, $parentSegment->segment_length, '0', STR_PAD_LEFT);
        $secondSegmentValue = str_pad(currentTeamId(), $teamSegment->segment_length, '0', STR_PAD_LEFT);
        $thirdSegmentValue = str_pad($accountValue->segment_value, $accountSegment->segment_length, '0', STR_PAD_LEFT);

        // Verify default handler was applied
        $this->assertEquals("$firstSegmentValue-$secondSegmentValue-$thirdSegmentValue", $account->account_segments_descriptor);
    }

    /**
     * Test that descriptors update when segment value descriptions change
     */
    public function test_it_updates_descriptors_on_value_change()
    {
        $segmentValues = $this->createTestSegmentValues();

        // Create account
        $account = $this->segmentService->createAccount(new CreateAccountDto([
            'segment_value_ids' => array_column($segmentValues, 'id'),
            'is_active' => true,
            'allow_manual_entry' => true,
            'account_type' => 'asset',
            'team_id' => 1,
        ]));

        $account->refresh();
        $originalDescriptor = $account->account_segments_descriptor;

        // Update segment value description
        $segmentValue = SegmentValue::find($segmentValues[0]['id']);
        $segmentValue->segment_value = '31';
        $segmentValue->save();

        // Trigger should update all accounts using this value
        $account->refresh();

        $this->assertNotEquals($originalDescriptor, $account->account_segments_descriptor);
        $this->assertStringContainsString('31', $account->account_segments_descriptor);
    }

    /**
     * Test searching accounts by segment pattern
     */
    public function test_it_searches_accounts_by_pattern()
    {
        // Create multiple accounts
        $this->createMultipleTestAccounts();

        // Search for all accounts with team "07"
        $teamValue = SegmentValue::where('segment_value', '07')->first();
        $pattern = [
            1 => null,  // Any parent team
            2 => $teamValue->id, // Specific team
            3 => null   // Any account
        ];

        $results = $this->segmentService->searchAccountsByPattern($pattern);

        $this->assertGreaterThan(0, $results->count());
        foreach ($results as $account) {
            $this->assertStringContainsString('-07-', $account->account_segments_descriptor);
        }
    }

    /**
     * Test segment structure validation
     */
    public function test_it_validates_segment_structure()
    {
        // Validation should pass with proper structure
        $validation = $this->segmentService->validateSegmentStructure();

        // Create gap in positions to test validation
        DB::table('fin_account_segments')
            ->where('segment_position', 2)
            ->update(['segment_position' => 4]);

        $validation = $this->segmentService->validateSegmentStructure();

        $this->assertCount(1, $validation);
    }

    /**
     * Test that segment positions are reordered correctly
     */
    public function test_it_reorders_segment_positions()
    {
        // Delete middle segment
        AccountSegment::where('segment_position', 2)->forceDelete();

        // Reorder positions
        AccountSegment::reorderPositions();

        // Verify positions are sequential
        $segments = AccountSegment::orderBy('segment_position')->get();

        $this->assertEquals(1, $segments[0]->segment_position);
        $this->assertEquals(2, $segments[1]->segment_position);
        $this->assertCount(2, $segments);
    }

    /**
     * Test that accounts cannot be created with missing segments
     */
    public function test_it_prevents_incomplete_segment_combinations()
    {
        $segmentValues = $this->createTestSegmentValues();

        // Try to create account with only 2 segments (missing one)
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(__('error-with-values-missing-value-value-for-segment-position'));

        $this->segmentService->createAccount(new CreateAccountDto([
            'segment_value_ids' => [
                $segmentValues[0]['id'],
                $segmentValues[1]['id'],
                // Missing third segment
            ],
            'is_active' => true,
            'allow_manual_entry' => true,
            'account_type' => 'asset',
            'team_id' => 1,
        ]));
    }

    /**
     * Test that inactive segment values cannot be used for new accounts
     */
    public function test_it_prevents_using_inactive_segment_values()
    {
        $segmentValues = $this->createTestSegmentValues();

        // Deactivate one segment value
        $segmentValue = SegmentValue::find($segmentValues[0]['id']);
        $segmentValue->is_active = false;
        $segmentValue->save();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(__('error-with-values-inactive-value-value-for-segment-position'));

        $this->segmentService->createAccount(new CreateAccountDto([
            'segment_value_ids' => array_column($segmentValues, 'id'),
            'is_active' => true,
            'allow_manual_entry' => true,
        ]));
    }

    // Helper Methods

    private function setupTestSegmentStructure()
    {
        // Clean up any existing structure
        DB::table('fin_account_segment_assignments')->delete();
        DB::table('fin_gl_accounts')->delete();
        DB::table('fin_segment_values')->delete();
        DB::table('fin_account_segments')->delete();

        // Create default 3-segment structure
        $this->segmentService->createOrUpdateSegment(new CreateOrUpdateSegmentDto([
            'segment_description' => 'Parent Team',
            'segment_position' => 1,
            'segment_length' => 2,
        ]));

        $this->segmentService->createOrUpdateSegment(new CreateOrUpdateSegmentDto([
            'segment_description' => 'Team',
            'segment_position' => 2,
            'segment_length' => 2,
        ]));

        $this->segmentService->createOrUpdateSegment(new CreateOrUpdateSegmentDto([
            'segment_description' => 'Natural Account',
            'segment_position' => 3,
            'segment_length' => 4,
        ]));
    }

    private function createTestSegmentValues(): array
    {
        $segments = AccountSegment::orderBy('segment_position')->get();
        $values = [];

        // Create value for parent team
        $values[] = $this->segmentService->createSegmentValue(new CreateSegmentValueDto([
            'segment_definition_id' => $segments[0]->id,
            'segment_value' => '10',
            'segment_description' => 'Headquarters',
            'is_active' => true,
        ]))->toArray();

        // Create value for team
        $values[] = $this->segmentService->createSegmentValue(new CreateSegmentValueDto([
            'segment_definition_id' => $segments[1]->id,
            'segment_value' => '03',
            'segment_description' => 'Sales Team',
            'is_active' => true,
        ]))->toArray();

        // Create value for natural account
        $values[] = $this->segmentService->createSegmentValue(new CreateSegmentValueDto([
            'segment_definition_id' => $segments[2]->id,
            'segment_value' => '4000',
            'segment_description' => 'Cash',
            'is_active' => true,
        ]))->toArray();

        return $values;
    }

    private function createMultipleTestAccounts(): void
    {
        $segments = AccountSegment::orderBy('segment_position')->get();

        // Create various combinations
        $parentTeams = ['15', '25'];
        $teams = ['07', '08'];
        $accounts = ['4004', '5005', '6006'];

        foreach ($parentTeams as $pt) {
            $this->segmentService->createSegmentValue(new CreateSegmentValueDto([
                 'segment_definition_id' => $segments[0]->id,
                 'segment_value' => $pt,
                 'segment_description' => "Parent $pt",
                 'is_active' => true,
             ]));
        }

        foreach ($teams as $t) {
            $this->segmentService->createSegmentValue(new CreateSegmentValueDto([
                'segment_definition_id' => $segments[1]->id,
                'segment_value' => $t,
                'segment_description' => "Team $t",
                'is_active' => true,
            ]));
        }

        foreach ($accounts as $a) {
            $this->segmentService->createSegmentValue(new CreateSegmentValueDto([
                'segment_definition_id' => $segments[2]->id,
                'segment_value' => $a,
                'segment_description' => "Account $a",
                'is_active' => true,
            ]));
        }

        foreach ($parentTeams as $pt) {
            $ptValue = SegmentValue::where('segment_value', $pt)->first();

            foreach ($teams as $t) {
                $tValue = SegmentValue::where('segment_value', $t)->first();

                foreach ($accounts as $a) {
                    $aValue = SegmentValue::where('segment_value', $a)->first();

                    try {
                        $this->segmentService->createAccount(new CreateAccountDto([
                            'segment_value_ids' => [$ptValue->id, $tValue->id, $aValue->id],
                            'is_active' => true,
                            'allow_manual_entry' => true,
                        ]));
                    } catch (\Exception $e) {
                        // Ignore duplicates in test data creation
                    }
                }
            }
        }
    }
}

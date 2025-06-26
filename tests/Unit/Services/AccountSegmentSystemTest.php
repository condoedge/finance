<?php

namespace Tests\Unit\Services;

use Condoedge\Finance\Models\GlAccount;
use Condoedge\Finance\Models\AccountSegment;
use Condoedge\Finance\Models\SegmentValue;
use Condoedge\Finance\Models\AccountSegmentAssignment;
use Condoedge\Finance\Services\AccountSegmentService;
use Condoedge\Finance\Services\Account\GlAccountService;
use Condoedge\Finance\Models\Dto\Gl\CreateAccountFromSegmentsDto;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Test the new segment-based account system
 */
class AccountSegmentSystemTest extends TestCase
{
    use RefreshDatabase;
    
    protected AccountSegmentService $segmentService;
    protected GlAccountService $accountService;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->segmentService = app(AccountSegmentService::class);
        $this->accountService = app(GlAccountService::class);
    }
    
    public function test_it_can_setup_default_segment_structure()
    {
        // Setup default structure
        $this->segmentService->setupDefaultSegmentStructure();
        
        // Verify segments were created
        $segments = AccountSegment::getAllOrdered();
        $this->assertCount(3, $segments);
        
        $this->assertEquals('Parent Team', $segments[0]->segment_description);
        $this->assertEquals(1, $segments[0]->segment_position);
        $this->assertEquals(2, $segments[0]->segment_length);
        
        $this->assertEquals('Team', $segments[1]->segment_description);
        $this->assertEquals(2, $segments[1]->segment_position);
        $this->assertEquals(2, $segments[1]->segment_length);
        
        $this->assertEquals('Natural Account', $segments[2]->segment_description);
        $this->assertEquals(3, $segments[2]->segment_position);
        $this->assertEquals(4, $segments[2]->segment_length);
    }
    
    public function test_it_can_create_segment_values()
    {
        // Setup structure first
        $this->segmentService->setupDefaultSegmentStructure();
        
        // Create segment values
        $parentTeam = $this->segmentService->createSegmentValue(1, '10', 'parent_team_10');
        $team = $this->segmentService->createSegmentValue(2, '03', 'team_03');
        $account = $this->segmentService->createSegmentValue(3, '4000', 'Cash Account');
        
        // Verify values were created correctly
        $this->assertEquals('10', $parentTeam->segment_value);
        $this->assertEquals('parent_team_10', $parentTeam->segment_description);
        
        $this->assertEquals('03', $team->segment_value);
        $this->assertEquals('team_03', $team->segment_description);
        
        $this->assertEquals('4000', $account->segment_value);
        $this->assertEquals('Cash Account', $account->segment_description);
    }
    
    public function test_it_validates_segment_value_length()
    {
        $this->segmentService->setupDefaultSegmentStructure();
        
        // Try to create invalid length segment value
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Segment value length must be 2 characters');
        
        $this->segmentService->createSegmentValue(1, '100', 'invalid_length'); // Position 1 expects 2 chars
    }
    
    public function test_it_can_validate_segment_combinations()
    {
        $this->segmentService->setupDefaultSegmentStructure();
        $this->segmentService->setupSampleSegmentValues();
        
        // Valid combination
        $validCombination = [1 => '10', 2 => '03', 3 => '4000'];
        $this->assertTrue($this->segmentService->validateSegmentCombination($validCombination));
        
        // Invalid combination (missing segment)
        $invalidCombination = [1 => '10', 3 => '4000']; // Missing segment 2
        $this->assertFalse($this->segmentService->validateSegmentCombination($invalidCombination));
        
        // Invalid combination (non-existent segment value)
        $nonExistentCombination = [1 => '99', 2 => '03', 3 => '4000']; // '99' doesn't exist
        $this->assertFalse($this->segmentService->validateSegmentCombination($nonExistentCombination));
    }
    
    public function test_it_can_create_accounts_from_segments()
    {
        $this->segmentService->setupDefaultSegmentStructure();
        $this->segmentService->setupSampleSegmentValues();
        
        // Create account from segments
        $segmentCodes = [1 => '10', 2 => '03', 3 => '4000'];
        $attributes = [
            'account_type' => 'ASSET',
            'team_id' => 1,
            'account_description' => 'Test Cash Account',
        ];
        
        $account = $this->segmentService->createAccount($segmentCodes, $attributes);
        
        // Verify account was created correctly
        $this->assertEquals('10-03-4000', $account->account_id);
        $this->assertEquals('Test Cash Account', $account->account_description);
        $this->assertEquals('ASSET', $account->account_type);
        $this->assertEquals(1, $account->team_id);
        
        // Verify segment assignments were created
        $assignments = AccountSegmentAssignment::getForAccount($account->id);
        $this->assertCount(3, $assignments);
        
        // Verify segments can be retrieved
        $segments = $account->segments;
        $this->assertEquals(['10', '03', '4000'], array_values($segments));
    }
    
    public function test_it_can_create_accounts_using_gl_account_service()
    {
        $this->segmentService->setupDefaultSegmentStructure();
        $this->segmentService->setupSampleSegmentValues();
        
        // Create account using GL Account Service
        $segmentCodes = [1 => '10', 2 => '03', 3 => '1105'];
        $attributes = [
            'account_type' => 'EXPENSE',
            'team_id' => 1,
        ];
        
        $account = $this->accountService->createAccountFromSegments($segmentCodes, $attributes);
        
        // Verify account was created
        $this->assertEquals('10-03-1105', $account->account_id);
        $this->assertEquals('EXPENSE', $account->account_type);
        
        // Verify auto-generated description
        $this->assertStringContains('parent_team_10', $account->account_description);
        $this->assertStringContains('team_03', $account->account_description);
        $this->assertStringContains('Material Expense', $account->account_description);
    }
    
    public function test_it_can_create_accounts_using_dto()
    {
        $this->segmentService->setupDefaultSegmentStructure();
        $this->segmentService->setupSampleSegmentValues();
        
        // Create account using DTO
        $dto = CreateAccountFromSegmentsDto::create(
            [1 => '20', 2 => '04', 3 => '4105'],
            'EXPENSE',
            1,
            'Custom Fuel Account'
        );
        
        $account = $this->accountService->createAccountFromDto($dto);
        
        $this->assertEquals('20-04-4105', $account->account_id);
        $this->assertEquals('Custom Fuel Account', $account->account_description);
        $this->assertEquals('EXPENSE', $account->account_type);
    }
    
    public function test_it_can_create_dto_from_account_id()
    {
        $dto = CreateAccountFromSegmentsDto::fromAccountId(
            '10-03-4000',
            'ASSET',
            1,
            'Cash Account'
        );
        
        $this->assertEquals([1 => '10', 2 => '03', 3 => '4000'], $dto->segmentCodes);
        $this->assertEquals('10-03-4000', $dto->getAccountId());
        $this->assertEquals('ASSET', $dto->accountType);
    }
    
    public function test_it_prevents_duplicate_accounts()
    {
        $this->segmentService->setupDefaultSegmentStructure();
        $this->segmentService->setupSampleSegmentValues();
        
        // Create first account
        $segmentCodes = [1 => '10', 2 => '03', 3 => '4000'];
        $attributes = [
            'account_type' => 'ASSET',
            'team_id' => 1,
        ];
        
        $account1 = $this->segmentService->createAccount($segmentCodes, $attributes);
        
        // Try to create duplicate
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->expectExceptionMessage('Account 10-03-4000 already exists');
        
        $this->segmentService->createAccount($segmentCodes, $attributes);
    }
    
    public function test_it_can_find_or_create_accounts()
    {
        $this->segmentService->setupDefaultSegmentStructure();
        $this->segmentService->setupSampleSegmentValues();
        
        $segmentCodes = [1 => '10', 2 => '03', 3 => '4000'];
        $attributes = [
            'account_type' => 'ASSET',
            'team_id' => 1,
        ];
        
        // First call creates
        $account1 = $this->segmentService->findOrCreateAccount($segmentCodes, $attributes);
        $this->assertEquals('10-03-4000', $account1->account_id);
        
        // Second call finds existing
        $account2 = $this->segmentService->findOrCreateAccount($segmentCodes, $attributes);
        $this->assertEquals($account1->id, $account2->id);
    }
    
    public function test_it_can_parse_and_build_account_ids()
    {
        $accountId = '10-03-4000';
        
        // Parse account ID
        $segments = $this->segmentService->parseAccountId($accountId);
        $this->assertEquals([1 => '10', 2 => '03', 3 => '4000'], $segments);
        
        // Build account ID from segments
        $rebuiltId = $this->segmentService->buildAccountId($segments);
        $this->assertEquals($accountId, $rebuiltId);
    }
    
    public function test_it_can_get_account_format_mask()
    {
        $this->segmentService->setupDefaultSegmentStructure();
        
        $formatMask = $this->segmentService->getAccountFormatMask();
        $this->assertEquals('XX-XX-XXXX', $formatMask);
    }
    
    public function test_it_can_get_segment_usage_statistics()
    {
        $this->segmentService->setupDefaultSegmentStructure();
        $this->segmentService->setupSampleSegmentValues();
        
        // Create accounts that use the same segment value
        $segmentValue = SegmentValue::findByPositionAndValue(1, '10');
        
        $this->segmentService->createAccount([1 => '10', 2 => '03', 3 => '4000'], [
            'account_type' => 'ASSET', 'team_id' => 1
        ]);
        
        $this->segmentService->createAccount([1 => '10', 2 => '04', 3 => '1105'], [
            'account_type' => 'EXPENSE', 'team_id' => 1
        ]);
        
        // Get usage statistics
        $usage = $this->segmentService->getSegmentValueUsage($segmentValue->id);
        
        $this->assertEquals(2, $usage['usage_count']);
        $this->assertFalse($usage['can_be_deleted']);
        $this->assertCount(2, $usage['accounts_using']);
    }
    
    public function test_it_validates_segment_structure_consistency()
    {
        // Empty structure should have issues
        $issues = $this->segmentService->validateSegmentStructure();
        $this->assertNotEmpty($issues);
        
        // Setup structure
        $this->segmentService->setupDefaultSegmentStructure();
        
        // Should still have issues (no segment values)
        $issues = $this->segmentService->validateSegmentStructure();
        $this->assertNotEmpty($issues);
        
        // Add sample values
        $this->segmentService->setupSampleSegmentValues();
        
        // Should be valid now
        $issues = $this->segmentService->validateSegmentStructure();
        $this->assertEmpty($issues);
    }
}

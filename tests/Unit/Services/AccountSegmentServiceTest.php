<?php

namespace Condoedge\Finance\Tests\Unit\Services;

use Condoedge\Finance\Tests\TestCase;
use Condoedge\Finance\Services\AccountSegmentService;
use Condoedge\Finance\Models\AccountSegment;
use Condoedge\Finance\Models\SegmentValue;
use Condoedge\Finance\Models\GlAccount;
use Condoedge\Finance\Models\AccountSegmentAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

class AccountSegmentServiceTest extends TestCase
{
    use RefreshDatabase;
    
    protected AccountSegmentService $service;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AccountSegmentService::class);
    }
    
    #[Test]
    public function it_can_setup_default_segment_structure()
    {
        $this->service->setupDefaultSegmentStructure();
        
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
    
    #[Test]
    public function it_can_create_segment_value_with_validation()
    {
        $this->service->setupDefaultSegmentStructure();
        
        $value = $this->service->createSegmentValue(1, '10', 'Parent Team 10');
        
        $this->assertInstanceOf(SegmentValue::class, $value);
        $this->assertEquals('10', $value->segment_value);
        $this->assertEquals('Parent Team 10', $value->segment_description);
        $this->assertTrue($value->is_active);
        
        // Test validation - wrong length
        $this->expectException(\InvalidArgumentException::class);
        $this->service->createSegmentValue(1, '100', 'Too Long'); // Should be 2 chars
    }
    
    #[Test]
    public function it_can_create_account_from_segments()
    {
        $this->service->setupDefaultSegmentStructure();
        $this->service->setupSampleSegmentValues();
        
        $segmentCodes = [1 => '10', 2 => '03', 3 => '4000'];
        $attributes = [
            'account_description' => 'Test Cash Account',
            'account_type' => 'asset',
            'team_id' => 1,
        ];
        
        $account = $this->service->createAccount($segmentCodes, $attributes);
        
        $this->assertInstanceOf(GlAccount::class, $account);
        $this->assertEquals('10-03-4000', $account->account_id);
        $this->assertEquals('Test Cash Account', $account->account_description);
        $this->assertEquals('asset', $account->account_type);
        
        // Verify segment assignments
        $assignments = AccountSegmentAssignment::getForAccount($account->id);
        $this->assertCount(3, $assignments);
    }
    
    #[Test]
    public function it_can_parse_and_build_account_ids()
    {
        $accountId = '10-03-4000';
        $parsed = $this->service->parseAccountId($accountId);
        
        $this->assertEquals([1 => '10', 2 => '03', 3 => '4000'], $parsed);
        
        $built = $this->service->buildAccountId($parsed);
        $this->assertEquals($accountId, $built);
    }
    
    #[Test]
    public function it_can_validate_segment_combinations()
    {
        $this->service->setupDefaultSegmentStructure();
        $this->service->setupSampleSegmentValues();
        
        // Valid combination
        $valid = $this->service->validateSegmentCombination([1 => '10', 2 => '03', 3 => '4000']);
        $this->assertTrue($valid);
        
        // Invalid - missing segment
        $invalid = $this->service->validateSegmentCombination([1 => '10', 3 => '4000']);
        $this->assertFalse($invalid);
        
        // Invalid - non-existent value
        $invalid = $this->service->validateSegmentCombination([1 => '99', 2 => '03', 3 => '4000']);
        $this->assertFalse($invalid);
    }
    
    #[Test]
    public function it_can_get_account_format_mask()
    {
        $this->service->setupDefaultSegmentStructure();
        
        $mask = $this->service->getAccountFormatMask();
        $this->assertEquals('XX-XX-XXXX', $mask);
    }
    
    #[Test]
    public function it_can_search_accounts_by_segment_pattern()
    {
        $this->service->setupDefaultSegmentStructure();
        $this->service->setupSampleSegmentValues();
        
        // Create some accounts
        $this->service->createAccount([1 => '10', 2 => '03', 3 => '4000'], ['team_id' => 1]);
        $this->service->createAccount([1 => '10', 2 => '03', 3 => '4001'], ['team_id' => 1]);
        $this->service->createAccount([1 => '10', 2 => '04', 3 => '4000'], ['team_id' => 1]);
        $this->service->createAccount([1 => '20', 2 => '03', 3 => '4000'], ['team_id' => 1]);
        
        // Search for all accounts with team '03'
        $results = $this->service->searchAccountsBySegmentPattern(['*', '03', '*'], 1);
        $this->assertCount(3, $results);
        
        // Search for specific pattern
        $results = $this->service->searchAccountsBySegmentPattern(['10', '03', '*'], 1);
        $this->assertCount(2, $results);
    }
    
    #[Test]
    public function it_can_bulk_create_accounts()
    {
        $this->service->setupDefaultSegmentStructure();
        $this->service->setupSampleSegmentValues();
        
        $combinations = [
            [1 => '10', 2 => '03', 3 => '4000'],
            [1 => '10', 2 => '03', 3 => '4001'],
            [1 => '10', 2 => '04', 3 => '1105'],
        ];
        
        $baseAttributes = [
            'account_type' => 'asset',
            'team_id' => 1,
        ];
        
        $accounts = $this->service->bulkCreateAccounts($combinations, $baseAttributes);
        
        $this->assertCount(3, $accounts);
        $this->assertEquals('10-03-4000', $accounts[0]->account_id);
        $this->assertEquals('10-03-4001', $accounts[1]->account_id);
        $this->assertEquals('10-04-1105', $accounts[2]->account_id);
    }
    
    #[Test]
    public function it_can_validate_segment_structure()
    {
        // Empty structure should have issues
        $issues = $this->service->validateSegmentStructure();
        $this->assertNotEmpty($issues);
        
        // Setup complete structure
        $this->service->setupDefaultSegmentStructure();
        $this->service->setupSampleSegmentValues();
        
        $issues = $this->service->validateSegmentStructure();
        $this->assertEmpty($issues);
    }
    
    #[Test]
    public function it_prevents_duplicate_segment_values()
    {
        $this->service->setupDefaultSegmentStructure();
        
        $this->service->createSegmentValue(1, '10', 'First');
        
        $this->expectException(\Exception::class);
        $this->service->createSegmentValue(1, '10', 'Duplicate');
    }
    
    #[Test]
    public function it_prevents_deleting_segment_values_in_use()
    {
        $this->service->setupDefaultSegmentStructure();
        $this->service->setupSampleSegmentValues();
        
        // Create an account using segment value
        $this->service->createAccount([1 => '10', 2 => '03', 3 => '4000'], ['team_id' => 1]);
        
        // Try to delete used segment value
        $segmentValue = SegmentValue::findByPositionAndValue(1, '10');
        $this->assertFalse($segmentValue->canBeDeleted());
        
        // Unused value can be deleted
        $unusedValue = $this->service->createSegmentValue(1, '99', 'Unused');
        $this->assertTrue($unusedValue->canBeDeleted());
    }
    
    #[Test]
    public function it_generates_account_description_from_segments()
    {
        $this->service->setupDefaultSegmentStructure();
        $this->service->setupSampleSegmentValues();
        
        $description = $this->service->getAccountDescription([1 => '10', 2 => '03', 3 => '4000']);
        $this->assertEquals('parent_team_10 - team_03 - Cash Account', $description);
    }
}

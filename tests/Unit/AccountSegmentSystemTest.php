<?php

namespace Condoedge\Finance\Tests\Unit;

use Condoedge\Finance\Models\GlAccount;
use Condoedge\Finance\Models\AccountSegment;
use Condoedge\Finance\Models\SegmentValue;
use Condoedge\Finance\Models\AccountSegmentAssignment;
use Condoedge\Finance\Facades\AccountSegmentService;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;

class AccountSegmentSystemTest extends TestCase
{
    use RefreshDatabase;
    
    public function test_it_can_create_segment_structure()
    {
        // Create segment structure
        AccountSegmentService::setupDefaultSegmentStructure();
        
        // Verify segments created
        $this->assertDatabaseCount('fin_account_segments', 3);
        
        $segment1 = AccountSegment::getByPosition(1);
        $this->assertEquals('Parent Team', $segment1->segment_description);
        $this->assertEquals(2, $segment1->segment_length);
        
        $segment2 = AccountSegment::getByPosition(2);
        $this->assertEquals('Team', $segment2->segment_description);
        $this->assertEquals(2, $segment2->segment_length);
        
        $segment3 = AccountSegment::getByPosition(3);
        $this->assertEquals('Natural Account', $segment3->segment_description);
        $this->assertEquals(4, $segment3->segment_length);
    }
    
    public function test_it_can_create_segment_values()
    {
        // Setup structure first
        AccountSegmentService::setupDefaultSegmentStructure();
        
        // Create segment values
        $value1 = AccountSegmentService::createSegmentValue(1, '10', 'Parent Team 10');
        $value2 = AccountSegmentService::createSegmentValue(2, '03', 'Team 03');
        $value3 = AccountSegmentService::createSegmentValue(3, '4000', 'Cash Account');
        
        // Verify values created
        $this->assertInstanceOf(SegmentValue::class, $value1);
        $this->assertEquals('10', $value1->segment_value);
        $this->assertEquals('Parent Team 10', $value1->segment_description);
        $this->assertTrue($value1->is_active);
    }
    
    public function test_it_validates_segment_value_length()
    {
        AccountSegmentService::setupDefaultSegmentStructure();
        
        // Try to create value with wrong length
        $this->expectException(\InvalidArgumentException::class);
        AccountSegmentService::createSegmentValue(1, '123', 'Too Long'); // Position 1 expects 2 chars
    }
    
    public function test_it_can_create_account_from_segments()
    {
        // Setup
        $this->setupTeam();
        AccountSegmentService::setupDefaultSegmentStructure();
        AccountSegmentService::setupSampleSegmentValues();
        
        // Create account
        $account = AccountSegmentService::createAccount(
            [1 => '10', 2 => '03', 3 => '4000'],
            [
                'account_description' => 'Main Cash Account',
                'account_type' => 'asset',
                'is_active' => true,
                'allow_manual_entry' => true,
                'team_id' => $this->team->id,
            ]
        );
        
        // Verify account created
        $this->assertInstanceOf(GlAccount::class, $account);
        $this->assertEquals('10-03-4000', $account->account_id);
        $this->assertEquals('Main Cash Account', $account->account_description);
        $this->assertEquals('asset', $account->account_type->value);
        $this->assertTrue($account->is_active);
        $this->assertTrue($account->allow_manual_entry);
        
        // Verify segment assignments
        $this->assertDatabaseCount('fin_account_segment_assignments', 3);
        $assignments = AccountSegmentAssignment::where('account_id', $account->id)->count();
        $this->assertEquals(3, $assignments);
    }
    
    public function test_it_prevents_duplicate_accounts()
    {
        // Setup
        $this->setupTeam();
        AccountSegmentService::setupDefaultSegmentStructure();
        AccountSegmentService::setupSampleSegmentValues();
        
        // Create first account
        $account1 = AccountSegmentService::createAccount(
            [1 => '10', 2 => '03', 3 => '4000'],
            [
                'account_description' => 'First Account',
                'account_type' => 'asset',
                'team_id' => $this->team->id,
            ]
        );
        
        // Try to create duplicate - should return existing
        $account2 = AccountSegmentService::createAccount(
            [1 => '10', 2 => '03', 3 => '4000'],
            [
                'account_description' => 'Second Account',
                'account_type' => 'asset',
                'team_id' => $this->team->id,
            ]
        );
        
        $this->assertEquals($account1->id, $account2->id);
        $this->assertDatabaseCount('fin_gl_accounts', 1);
    }
    
    public function test_it_can_parse_account_id()
    {
        $segments = AccountSegmentService::parseAccountId('10-03-4000');
        
        $this->assertEquals([
            1 => '10',
            2 => '03',
            3 => '4000',
        ], $segments);
    }
    
    public function test_it_can_search_accounts_by_pattern()
    {
        // Setup
        $this->setupTeam();
        AccountSegmentService::setupDefaultSegmentStructure();
        AccountSegmentService::setupSampleSegmentValues();
        
        // Create multiple accounts
        AccountSegmentService::createAccount([1 => '10', 2 => '03', 3 => '4000'], [
            'account_type' => 'asset',
            'team_id' => $this->team->id,
        ]);
        AccountSegmentService::createAccount([1 => '10', 2 => '03', 3 => '4001'], [
            'account_type' => 'asset',
            'team_id' => $this->team->id,
        ]);
        AccountSegmentService::createAccount([1 => '10', 2 => '04', 3 => '4000'], [
            'account_type' => 'asset',
            'team_id' => $this->team->id,
        ]);
        AccountSegmentService::createAccount([1 => '20', 2 => '03', 3 => '4000'], [
            'account_type' => 'asset',
            'team_id' => $this->team->id,
        ]);
        
        // Search for all accounts with team '03'
        $results = AccountSegmentService::searchAccountsBySegmentPattern(
            [1 => '*', 2 => '03', 3 => '*'],
            $this->team->id
        );
        
        $this->assertCount(3, $results);
        $this->assertTrue($results->pluck('account_id')->contains('10-03-4000'));
        $this->assertTrue($results->pluck('account_id')->contains('10-03-4001'));
        $this->assertTrue($results->pluck('account_id')->contains('20-03-4000'));
    }
    
    public function test_it_validates_segment_combination()
    {
        AccountSegmentService::setupDefaultSegmentStructure();
        AccountSegmentService::setupSampleSegmentValues();
        
        // Valid combination
        $valid = AccountSegmentService::validateSegmentCombination([
            1 => '10',
            2 => '03',
            3 => '4000',
        ]);
        $this->assertTrue($valid);
        
        // Invalid - missing position
        $invalid = AccountSegmentService::validateSegmentCombination([
            1 => '10',
            3 => '4000',
        ]);
        $this->assertFalse($invalid);
        
        // Invalid - non-existent value
        $invalid = AccountSegmentService::validateSegmentCombination([
            1 => '99',
            2 => '03',
            3 => '4000',
        ]);
        $this->assertFalse($invalid);
    }
    
    public function test_it_tracks_segment_value_usage()
    {
        // Setup
        $this->setupTeam();
        AccountSegmentService::setupDefaultSegmentStructure();
        AccountSegmentService::setupSampleSegmentValues();
        
        // Get segment value
        $segmentValue = SegmentValue::findByPositionAndValue(1, '10');
        
        // Initially no usage
        $this->assertEquals(0, $segmentValue->getUsageCount());
        $this->assertTrue($segmentValue->canBeDeleted());
        
        // Create accounts using this value
        AccountSegmentService::createAccount([1 => '10', 2 => '03', 3 => '4000'], [
            'account_type' => 'asset',
            'team_id' => $this->team->id,
        ]);
        AccountSegmentService::createAccount([1 => '10', 2 => '04', 3 => '4001'], [
            'account_type' => 'asset',
            'team_id' => $this->team->id,
        ]);
        
        // Check usage
        $this->assertEquals(2, $segmentValue->getUsageCount());
        $this->assertFalse($segmentValue->canBeDeleted());
    }
    
    public function test_it_generates_account_format_mask()
    {
        AccountSegmentService::setupDefaultSegmentStructure();
        
        $mask = AccountSegmentService::getAccountFormatMask();
        $this->assertEquals('XX-XX-XXXX', $mask);
    }
    
    protected function setupTeam()
    {
        $this->team = \App\Models\Team::factory()->create();
        $this->actingAs(\App\Models\User::factory()->create());
        setCurrentTeamId($this->team->id);
    }
}

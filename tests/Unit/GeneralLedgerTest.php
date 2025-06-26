<?php

namespace Tests\Unit\Models;

use Condoedge\Finance\Models\FiscalYearSetup;
use Condoedge\Finance\Models\FiscalPeriod;
use Condoedge\Finance\Models\GlSequence;
use Condoedge\Finance\Models\GlTransactionHeader;
use Condoedge\Finance\Models\GlTransactionLine;
use Condoedge\Finance\Models\GlAccount;
use Tests\TestCase;
use Carbon\Carbon;

class GeneralLedgerTest extends TestCase
{
    protected $team;
    protected $fiscalYearSetup;
      public function setUp(): void
    {
        parent::setUp();
        
        // Create a test team using the CustomableTeam model
        $this->team = \Condoedge\Finance\Models\CustomableTeam::create([
            'team_name' => 'Test Team',
        ]);
        
        // Set up fiscal year starting May 1st
        $this->fiscalYearSetup = FiscalYearSetup::create([
            'team_id' => $this->team->id,
            'fiscal_start_date' => '2024-05-01',
            'is_active' => true,
        ]);
    }
    
    public function test_it_can_determine_fiscal_year_from_date()
    {
        // Date after fiscal start should be current fiscal year
        $date = Carbon::parse('2024-07-15');
        $fiscalYear = $this->fiscalYearSetup->getFiscalYear($date);
        $this->assertEquals(2024, $fiscalYear);
        
        // Date before fiscal start should be previous fiscal year
        $date = Carbon::parse('2024-03-15');
        $fiscalYear = $this->fiscalYearSetup->getFiscalYear($date);
        $this->assertEquals(2023, $fiscalYear);
    }
    
    public function test_it_can_get_fiscal_year_boundaries()
    {
        $fiscalYear = 2024;
        
        $start = $this->fiscalYearSetup->getFiscalYearStart($fiscalYear);
        $end = $this->fiscalYearSetup->getFiscalYearEnd($fiscalYear);
        
        $this->assertEquals('2024-05-01', $start->format('Y-m-d'));
        $this->assertEquals('2025-04-30', $end->format('Y-m-d'));
    }
    
    public function test_it_can_create_fiscal_periods_for_team()
    {
        $period = FiscalPeriod::create([
            'period_id' => 'per01-2024',
            'team_id' => $this->team->id,
            'fiscal_year' => 2024,
            'period_number' => 1,
            'start_date' => '2024-05-01',
            'end_date' => '2024-05-31',
            'is_open_gl' => true,
            'is_open_bnk' => true,
            'is_open_rm' => true,
            'is_open_pm' => true,
        ]);
        
        $this->assertInstanceOf(FiscalPeriod::class, $period);
        $this->assertEquals($this->team->id, $period->team_id);
        $this->assertTrue($period->isOpenForModule('GL'));
    }
    
    public function test_it_can_find_period_from_date()
    {
        FiscalPeriod::create([
            'period_id' => 'per01-2024',
            'team_id' => $this->team->id,
            'fiscal_year' => 2024,
            'period_number' => 1,
            'start_date' => '2024-05-01',
            'end_date' => '2024-05-31',
        ]);
        
        $date = Carbon::parse('2024-05-15');
        $period = FiscalPeriod::getPeriodFromDate($date, $this->team->id);
        
        $this->assertNotNull($period);
        $this->assertEquals('per01-2024', $period->period_id);
    }
    
    public function test_it_generates_sequence_numbers_for_team()
    {
        $sequence1 = GlSequence::getNextNumber($this->team->id, 'GL_TRANSACTION', 2024);
        $sequence2 = GlSequence::getNextNumber($this->team->id, 'GL_TRANSACTION', 2024);
        
        $this->assertEquals(1, $sequence1);
        $this->assertEquals(2, $sequence2);
    }
    
    public function test_it_generates_unique_transaction_ids_per_team()
    {
        $transactionId = GlTransactionHeader::generateTransactionId(
            $this->team->id, 
            GlTransactionHeader::TYPE_MANUAL_GL, 
            2024
        );
        
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{6}$/', $transactionId);
        $this->assertStringStartsWith('2024-01-', $transactionId);
    }
    
    public function test_it_can_create_gl_transaction_with_multi_tenancy()
    {
        // Create test accounts
        $assetAccount = GlAccount::factory()->create([
            'team_id' => $this->team->id,
            'account_id' => 'ASSET-001',
            'account_type' => GlAccount::TYPE_ASSET,
            'is_active' => true,
            'allow_manual_entry' => true,
        ]);
        
        $liabilityAccount = GlAccount::factory()->create([
            'team_id' => $this->team->id,
            'account_id' => 'LIAB-001',
            'account_type' => GlAccount::TYPE_LIABILITY,
            'is_active' => true,
            'allow_manual_entry' => true,
        ]);
        
        // Create fiscal period
        FiscalPeriod::create([
            'period_id' => 'per01-2024',
            'team_id' => $this->team->id,
            'fiscal_year' => 2024,
            'period_number' => 1,
            'start_date' => '2024-05-01',
            'end_date' => '2024-05-31',
        ]);
        
        // Create GL transaction
        $transactionId = GlTransactionHeader::generateTransactionId(
            $this->team->id, 
            GlTransactionHeader::TYPE_MANUAL_GL, 
            2024
        );
        
        $transaction = GlTransactionHeader::create([
            'gl_transaction_id' => $transactionId,
            'team_id' => $this->team->id,
            'fiscal_date' => '2024-05-15',
            'fiscal_period' => 'per01-2024',
            'gl_transaction_type' => GlTransactionHeader::TYPE_MANUAL_GL,
            'transaction_description' => 'Test GL Entry',
            'is_posted' => false,
            'is_balanced' => false,
        ]);
        
        // Add transaction lines
        GlTransactionLine::create([
            'gl_transaction_id' => $transactionId,
            'team_id' => $this->team->id,
            'account_id' => $assetAccount->account_id,
            'line_description' => 'Debit Asset',
            'debit_amount' => 1000.00,
            'credit_amount' => 0.00,
        ]);
        
        GlTransactionLine::create([
            'gl_transaction_id' => $transactionId,
            'team_id' => $this->team->id,
            'account_id' => $liabilityAccount->account_id,
            'line_description' => 'Credit Liability',
            'debit_amount' => 0.00,
            'credit_amount' => 1000.00,
        ]);
        
        // Verify transaction is balanced
        $this->assertTrue($transaction->checkIsBalanced());
        
        // Post the transaction
        $this->assertTrue($transaction->post());
        $this->assertTrue($transaction->fresh()->is_posted);
    }
      public function test_it_enforces_team_isolation_in_gl_data()
    {
        $otherTeam = \Condoedge\Finance\Models\CustomableTeam::create([
            'team_name' => 'Other Team',
        ]);
        
        // Create fiscal setup for both teams
        FiscalYearSetup::create([
            'team_id' => $this->team->id,
            'fiscal_start_date' => '2024-05-01',
            'is_active' => true,
        ]);
        
        FiscalYearSetup::create([
            'team_id' => $otherTeam->id,
            'fiscal_start_date' => '2024-01-01',
            'is_active' => true,
        ]);
        
        // Test that each team only sees their own data
        $thisTeamSetup = FiscalYearSetup::getActiveForTeam($this->team->id);
        $otherTeamSetup = FiscalYearSetup::getActiveForTeam($otherTeam->id);
        
        $this->assertEquals('2024-05-01', $thisTeamSetup->fiscal_start_date->format('Y-m-d'));
        $this->assertEquals('2024-01-01', $otherTeamSetup->fiscal_start_date->format('Y-m-d'));
    }
    
    public function test_it_prevents_posting_unbalanced_transactions()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Transaction is not balanced and cannot be posted.');
        
        $assetAccount = GlAccount::factory()->create([
            'team_id' => $this->team->id,
            'account_id' => 'ASSET-001',
            'is_active' => true,
            'allow_manual_entry' => true,
        ]);
        
        FiscalPeriod::create([
            'period_id' => 'per01-2024',
            'team_id' => $this->team->id,
            'fiscal_year' => 2024,
            'period_number' => 1,
            'start_date' => '2024-05-01',
            'end_date' => '2024-05-31',
        ]);
        
        $transactionId = GlTransactionHeader::generateTransactionId(
            $this->team->id, 
            GlTransactionHeader::TYPE_MANUAL_GL, 
            2024
        );
        
        $transaction = GlTransactionHeader::create([
            'gl_transaction_id' => $transactionId,
            'team_id' => $this->team->id,
            'fiscal_date' => '2024-05-15',
            'fiscal_period' => 'per01-2024',
            'gl_transaction_type' => GlTransactionHeader::TYPE_MANUAL_GL,
            'transaction_description' => 'Unbalanced Entry',
            'is_posted' => false,
            'is_balanced' => false,
        ]);
        
        // Only add debit side - transaction will be unbalanced
        GlTransactionLine::create([
            'gl_transaction_id' => $transactionId,
            'team_id' => $this->team->id,
            'account_id' => $assetAccount->account_id,
            'debit_amount' => 1000.00,
            'credit_amount' => 0.00,
        ]);
        
        // This should throw an exception
        $transaction->post();
    }
    
    public function test_it_prevents_posting_to_closed_periods()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Period is closed for this transaction type.');
        
        $assetAccount = GlAccount::factory()->create([
            'team_id' => $this->team->id,
            'account_id' => 'ASSET-001',
            'is_active' => true,
            'allow_manual_entry' => true,
        ]);
        
        $liabilityAccount = GlAccount::factory()->create([
            'team_id' => $this->team->id,
            'account_id' => 'LIAB-001',
            'is_active' => true,
            'allow_manual_entry' => true,
        ]);
        
        // Create closed period
        FiscalPeriod::create([
            'period_id' => 'per01-2024',
            'team_id' => $this->team->id,
            'fiscal_year' => 2024,
            'period_number' => 1,
            'start_date' => '2024-05-01',
            'end_date' => '2024-05-31',
            'is_open_gl' => false, // Period is closed for GL
        ]);
        
        $transactionId = GlTransactionHeader::generateTransactionId(
            $this->team->id, 
            GlTransactionHeader::TYPE_MANUAL_GL, 
            2024
        );
        
        $transaction = GlTransactionHeader::create([
            'gl_transaction_id' => $transactionId,
            'team_id' => $this->team->id,
            'fiscal_date' => '2024-05-15',
            'fiscal_period' => 'per01-2024',
            'gl_transaction_type' => GlTransactionHeader::TYPE_MANUAL_GL,
            'transaction_description' => 'Test Entry',
            'is_posted' => false,
            'is_balanced' => false,
        ]);
        
        GlTransactionLine::create([
            'gl_transaction_id' => $transactionId,
            'team_id' => $this->team->id,
            'account_id' => $assetAccount->account_id,
            'debit_amount' => 1000.00,
            'credit_amount' => 0.00,
        ]);
        
        GlTransactionLine::create([
            'gl_transaction_id' => $transactionId,
            'team_id' => $this->team->id,
            'account_id' => $liabilityAccount->account_id,
            'debit_amount' => 0.00,
            'credit_amount' => 1000.00,
        ]);
        
        // This should throw an exception
        $transaction->post();
    }
}

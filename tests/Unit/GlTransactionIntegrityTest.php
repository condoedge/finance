<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Condoedge\Finance\Models\FiscalYearSetup;
use Condoedge\Finance\Models\FiscalPeriod;
use Condoedge\Finance\Models\GlTransactionHeader;
use Condoedge\Finance\Models\GlTransactionLine;
use Condoedge\Finance\Models\Account;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class GlTransactionIntegrityTest extends TestCase
{
    use RefreshDatabase;
    
    protected $fiscalSetup;
    protected $fiscalPeriod;
    protected $account1;
    protected $account2;
    
    public function setUp(): void
    {
        parent::setUp();
        
        // Setup fiscal year
        $this->fiscalSetup = FiscalYearSetup::create([
            'company_fiscal_start_date' => '2024-05-01',
            'is_active' => true,
        ]);
        
        // Setup fiscal period
        $this->fiscalPeriod = FiscalPeriod::create([
            'period_id' => 'per01',
            'fiscal_year' => 2025,
            'period_number' => 1,
            'start_date' => '2024-05-01',
            'end_date' => '2024-05-31',
            'is_open_gl' => true,
            'is_open_bnk' => true,
            'is_open_rm' => true,
            'is_open_pm' => true,
        ]);
        
        // Setup accounts
        $this->account1 = Account::create([
            'account_id' => '01-100-1000',
            'account_description' => 'Cash Account',
            'is_active' => true,
            'allow_manual_entry' => true,
            'account_type' => 'ASSET',
        ]);
        
        $this->account2 = Account::create([
            'account_id' => '02-400-4000',
            'account_description' => 'Revenue Account',
            'is_active' => true,
            'allow_manual_entry' => true,
            'account_type' => 'REVENUE',
        ]);
    }
    
    public function test_fiscal_year_calculation_from_date()
    {
        // Test date after fiscal start in same calendar year
        $date1 = Carbon::parse('2024-06-15');
        $fiscalYear1 = FiscalYearSetup::getFiscalYearFromDate($date1);
        $this->assertEquals(2025, $fiscalYear1);
        
        // Test date before fiscal start in next calendar year
        $date2 = Carbon::parse('2025-03-15');
        $fiscalYear2 = FiscalYearSetup::getFiscalYearFromDate($date2);
        $this->assertEquals(2025, $fiscalYear2);
        
        // Test date after fiscal start in next calendar year
        $date3 = Carbon::parse('2025-05-15');
        $fiscalYear3 = FiscalYearSetup::getFiscalYearFromDate($date3);
        $this->assertEquals(2026, $fiscalYear3);
    }
    
    public function test_fiscal_period_identification()
    {
        $date = Carbon::parse('2024-05-15');
        $period = FiscalPeriod::getPeriodFromDate($date);
        
        $this->assertNotNull($period);
        $this->assertEquals('per01', $period->period_id);
        $this->assertEquals(2025, $period->fiscal_year);
    }
    
    public function test_gl_transaction_number_generation()
    {
        // First transaction
        $transaction1 = GlTransactionHeader::createTransaction([
            'fiscal_date' => '2024-05-15',
            'gl_transaction_type' => GlTransactionHeader::TYPE_MANUAL_GL,
            'transaction_description' => 'Test transaction 1',
        ]);
        
        $this->assertEquals(1, $transaction1->gl_transaction_number);
        $this->assertEquals('2025-01-000001', $transaction1->gl_transaction_id);
        
        // Second transaction should get next number
        $transaction2 = GlTransactionHeader::createTransaction([
            'fiscal_date' => '2024-05-16',
            'gl_transaction_type' => GlTransactionHeader::TYPE_BANK,
            'transaction_description' => 'Test transaction 2',
        ]);
        
        $this->assertEquals(2, $transaction2->gl_transaction_number);
        $this->assertEquals('2025-02-000002', $transaction2->gl_transaction_id);
    }
    
    public function test_gl_transaction_balance_validation()
    {
        $transaction = GlTransactionHeader::createTransaction([
            'fiscal_date' => '2024-05-15',
            'gl_transaction_type' => GlTransactionHeader::TYPE_MANUAL_GL,
            'transaction_description' => 'Balanced transaction test',
        ]);
        
        // Initially unbalanced
        $this->assertFalse($transaction->fresh()->is_balanced);
        
        // Add balanced lines
        GlTransactionLine::create([
            'gl_transaction_id' => $transaction->gl_transaction_id,
            'account_id' => $this->account1->account_id,
            'line_description' => 'Debit line',
            'debit_amount' => 1000,
            'credit_amount' => 0,
        ]);
        
        GlTransactionLine::create([
            'gl_transaction_id' => $transaction->gl_transaction_id,
            'account_id' => $this->account2->account_id,
            'line_description' => 'Credit line',
            'debit_amount' => 0,
            'credit_amount' => 1000,
        ]);
        
        // Should now be balanced (triggers update the header)
        $this->assertTrue($transaction->fresh()->is_balanced);
    }
    
    public function test_cannot_post_unbalanced_transaction()
    {
        $transaction = GlTransactionHeader::createTransaction([
            'fiscal_date' => '2024-05-15',
            'gl_transaction_type' => GlTransactionHeader::TYPE_MANUAL_GL,
            'transaction_description' => 'Unbalanced transaction test',
        ]);
        
        // Add only debit line (unbalanced)
        GlTransactionLine::create([
            'gl_transaction_id' => $transaction->gl_transaction_id,
            'account_id' => $this->account1->account_id,
            'line_description' => 'Debit line only',
            'debit_amount' => 1000,
            'credit_amount' => 0,
        ]);
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot post unbalanced transaction');
        
        $transaction->fresh()->post();
    }
    
    public function test_cannot_create_transaction_in_closed_period()
    {
        // Close GL period
        $this->fiscalPeriod->update(['is_open_gl' => false]);
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot post transaction to closed fiscal period');
        
        // Should fail due to trigger validation
        DB::table('fin_gl_transaction_headers')->insert([
            'gl_transaction_id' => '2025-01-000999',
            'gl_transaction_number' => 999,
            'fiscal_date' => '2024-05-15',
            'fiscal_year' => 2025,
            'fiscal_period' => 'per01',
            'gl_transaction_type' => GlTransactionHeader::TYPE_MANUAL_GL,
            'transaction_description' => 'Should fail',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
    
    public function test_cannot_use_inactive_account()
    {
        // Deactivate account
        $this->account1->update(['is_active' => false]);
        
        $transaction = GlTransactionHeader::createTransaction([
            'fiscal_date' => '2024-05-15',
            'gl_transaction_type' => GlTransactionHeader::TYPE_MANUAL_GL,
            'transaction_description' => 'Test with inactive account',
        ]);
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot use inactive account');
        
        // Should fail due to trigger validation
        DB::table('fin_gl_transaction_lines')->insert([
            'gl_transaction_id' => $transaction->gl_transaction_id,
            'account_id' => $this->account1->account_id,
            'line_description' => 'Should fail',
            'debit_amount' => 1000,
            'credit_amount' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
    
    public function test_cannot_use_no_manual_entry_account_in_manual_gl()
    {
        // Restrict manual entry
        $this->account1->update(['allow_manual_entry' => false]);
        
        $transaction = GlTransactionHeader::createTransaction([
            'fiscal_date' => '2024-05-15',
            'gl_transaction_type' => GlTransactionHeader::TYPE_MANUAL_GL,
            'transaction_description' => 'Test with no manual entry account',
        ]);
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Account does not allow manual entries');
        
        // Should fail due to trigger validation
        DB::table('fin_gl_transaction_lines')->insert([
            'gl_transaction_id' => $transaction->gl_transaction_id,
            'account_id' => $this->account1->account_id,
            'line_description' => 'Should fail',
            'debit_amount' => 1000,
            'credit_amount' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
    
    public function test_line_sequence_auto_generation()
    {
        $transaction = GlTransactionHeader::createTransaction([
            'fiscal_date' => '2024-05-15',
            'gl_transaction_type' => GlTransactionHeader::TYPE_MANUAL_GL,
            'transaction_description' => 'Sequence test',
        ]);
        
        $line1 = GlTransactionLine::create([
            'gl_transaction_id' => $transaction->gl_transaction_id,
            'account_id' => $this->account1->account_id,
            'line_description' => 'First line',
            'debit_amount' => 500,
            'credit_amount' => 0,
        ]);
        
        $line2 = GlTransactionLine::create([
            'gl_transaction_id' => $transaction->gl_transaction_id,
            'account_id' => $this->account2->account_id,
            'line_description' => 'Second line',
            'debit_amount' => 0,
            'credit_amount' => 500,
        ]);
        
        $this->assertEquals(1, $line1->line_sequence);
        $this->assertEquals(2, $line2->line_sequence);
    }
    
    public function test_cannot_modify_posted_transaction()
    {
        $transaction = GlTransactionHeader::createTransaction([
            'fiscal_date' => '2024-05-15',
            'gl_transaction_type' => GlTransactionHeader::TYPE_MANUAL_GL,
            'transaction_description' => 'Posted transaction test',
        ]);
        
        // Add balanced lines
        GlTransactionLine::create([
            'gl_transaction_id' => $transaction->gl_transaction_id,
            'account_id' => $this->account1->account_id,
            'debit_amount' => 1000,
            'credit_amount' => 0,
        ]);
        
        GlTransactionLine::create([
            'gl_transaction_id' => $transaction->gl_transaction_id,
            'account_id' => $this->account2->account_id,
            'debit_amount' => 0,
            'credit_amount' => 1000,
        ]);
        
        // Post the transaction
        $transaction->fresh()->post();
        $this->assertTrue($transaction->fresh()->is_posted);
        
        // Should not be able to modify
        $this->assertFalse($transaction->fresh()->canBeModified());
    }
}

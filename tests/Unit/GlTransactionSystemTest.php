<?php

namespace Condoedge\Finance\Tests\Unit;

use Condoedge\Finance\Models\GlTransactionHeader;
use Condoedge\Finance\Models\GlTransactionLine;
use Condoedge\Finance\Models\GlAccount;
use Condoedge\Finance\Models\FiscalPeriod;
use Condoedge\Finance\Models\FiscalYearSetup;
use Condoedge\Finance\Models\Dto\CreateGlTransactionDto;
use Condoedge\Finance\Services\GlTransactionService;
use Condoedge\Finance\Facades\AccountSegmentService;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;

class GlTransactionSystemTest extends TestCase
{
    use RefreshDatabase;
    
    protected GlTransactionService $service;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(GlTransactionService::class);
        $this->setupTestEnvironment();
    }
    
    #[Test]
    public function it_can_create_manual_gl_transaction()
    {
        // Prepare transaction data
        $dto = new CreateGlTransactionDto([
            'fiscal_date' => '2025-06-15',
            'transaction_description' => 'Test journal entry',
            'lines' => [
                [
                    'account_id' => '10-03-4000',
                    'line_description' => 'Cash debit',
                    'debit_amount' => 1000.00,
                    'credit_amount' => 0,
                ],
                [
                    'account_id' => '10-03-3000',
                    'line_description' => 'Equity credit',
                    'debit_amount' => 0,
                    'credit_amount' => 1000.00,
                ],
            ],
        ]);
        
        // Create transaction
        $transaction = $this->service->createManualGlTransaction($dto);
        
        // Verify transaction created
        $this->assertInstanceOf(GlTransactionHeader::class, $transaction);
        $this->assertEquals(GlTransactionHeader::TYPE_MANUAL_GL, $transaction->gl_transaction_type);
        $this->assertEquals('2025-06-15', $transaction->fiscal_date->format('Y-m-d'));
        $this->assertEquals('Test journal entry', $transaction->transaction_description);
        $this->assertEquals(2025, $transaction->fiscal_year);
        $this->assertTrue($transaction->is_balanced);
        $this->assertFalse($transaction->is_posted);
        
        // Verify lines created
        $this->assertCount(2, $transaction->lines);
        
        $debitLine = $transaction->lines->where('debit_amount', '>', 0)->first();
        $this->assertEquals('10-03-4000', $debitLine->account_id);
        $this->assertEquals(1000.00, $debitLine->debit_amount);
        $this->assertEquals(0, $debitLine->credit_amount);
        
        $creditLine = $transaction->lines->where('credit_amount', '>', 0)->first();
        $this->assertEquals('10-03-3000', $creditLine->account_id);
        $this->assertEquals(0, $creditLine->debit_amount);
        $this->assertEquals(1000.00, $creditLine->credit_amount);
    }
    
    #[Test]
    public function it_validates_transaction_balance()
    {
        // Unbalanced transaction
        $dto = new CreateGlTransactionDto([
            'fiscal_date' => '2025-06-15',
            'transaction_description' => 'Unbalanced entry',
            'lines' => [
                [
                    'account_id' => '10-03-4000',
                    'debit_amount' => 1000.00,
                    'credit_amount' => 0,
                ],
                [
                    'account_id' => '10-03-3000',
                    'debit_amount' => 0,
                    'credit_amount' => 900.00, // Not balanced!
                ],
            ],
        ]);
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Transaction must balance');
        
        $this->service->createManualGlTransaction($dto);
    }
    
    #[Test]
    public function it_validates_fiscal_period_is_open()
    {
        // Close the period for GL
        $period = FiscalPeriod::getPeriodFromDate(Carbon::parse('2025-06-15'));
        $period->update(['is_open_gl' => false]);
        
        $dto = new CreateGlTransactionDto([
            'fiscal_date' => '2025-06-15',
            'transaction_description' => 'Test entry',
            'lines' => [
                ['account_id' => '10-03-4000', 'debit_amount' => 100, 'credit_amount' => 0],
                ['account_id' => '10-03-3000', 'debit_amount' => 0, 'credit_amount' => 100],
            ],
        ]);
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('closed');
        
        $this->service->createManualGlTransaction($dto);
    }
    
    #[Test]
    public function it_validates_accounts_allow_manual_entry()
    {
        // Create account that doesn't allow manual entry
        $account = GlAccount::where('account_id', '10-03-4000')->first();
        $account->update(['allow_manual_entry' => false]);
        
        $dto = new CreateGlTransactionDto([
            'fiscal_date' => '2025-06-15',
            'transaction_description' => 'Test entry',
            'lines' => [
                ['account_id' => '10-03-4000', 'debit_amount' => 100, 'credit_amount' => 0],
                ['account_id' => '10-03-3000', 'debit_amount' => 0, 'credit_amount' => 100],
            ],
        ]);
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('manual entry');
        
        $this->service->createManualGlTransaction($dto);
    }
    
    #[Test]
    public function it_generates_sequential_transaction_numbers()
    {
        // Create multiple transactions
        $transactions = [];
        for ($i = 1; $i <= 3; $i++) {
            $dto = new CreateGlTransactionDto([
                'fiscal_date' => '2025-06-15',
                'transaction_description' => "Test entry {$i}",
                'lines' => [
                    ['account_id' => '10-03-4000', 'debit_amount' => 100, 'credit_amount' => 0],
                    ['account_id' => '10-03-3000', 'debit_amount' => 0, 'credit_amount' => 100],
                ],
            ]);
            
            $transactions[] = $this->service->createManualGlTransaction($dto);
        }
        
        // Verify sequential numbers
        $this->assertEquals(1, $transactions[0]->gl_transaction_number);
        $this->assertEquals(2, $transactions[1]->gl_transaction_number);
        $this->assertEquals(3, $transactions[2]->gl_transaction_number);
        
        // Verify transaction IDs
        $this->assertEquals('2025-01-000001', $transactions[0]->gl_transaction_id);
        $this->assertEquals('2025-01-000002', $transactions[1]->gl_transaction_id);
        $this->assertEquals('2025-01-000003', $transactions[2]->gl_transaction_id);
    }
    
    #[Test]
    public function it_can_post_transaction()
    {
        // Create transaction
        $dto = new CreateGlTransactionDto([
            'fiscal_date' => '2025-06-15',
            'transaction_description' => 'Test entry',
            'lines' => [
                ['account_id' => '10-03-4000', 'debit_amount' => 100, 'credit_amount' => 0],
                ['account_id' => '10-03-3000', 'debit_amount' => 0, 'credit_amount' => 100],
            ],
        ]);
        
        $transaction = $this->service->createManualGlTransaction($dto);
        $this->assertFalse($transaction->is_posted);
        $this->assertTrue($transaction->canBeModified());
        
        // Post transaction
        $this->service->postTransaction($transaction);
        
        $transaction->refresh();
        $this->assertTrue($transaction->is_posted);
        $this->assertFalse($transaction->canBeModified());
    }
    
    #[Test]
    public function it_cannot_post_unbalanced_transaction()
    {
        // Create transaction and manually make it unbalanced (simulating data corruption)
        $transaction = GlTransactionHeader::create([
            'gl_transaction_id' => '2025-01-999999',
            'gl_transaction_number' => 999999,
            'fiscal_date' => '2025-06-15',
            'fiscal_year' => 2025,
            'fiscal_period' => 1,
            'gl_transaction_type' => GlTransactionHeader::TYPE_MANUAL_GL,
            'transaction_description' => 'Corrupted entry',
            'team_id' => $this->team->id,
            'is_balanced' => false, // Force unbalanced
        ]);
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('unbalanced');
        
        $this->service->postTransaction($transaction);
    }
    
    #[Test]
    public function it_prevents_modification_of_posted_transaction()
    {
        // Create and post transaction
        $dto = new CreateGlTransactionDto([
            'fiscal_date' => '2025-06-15',
            'transaction_description' => 'Test entry',
            'lines' => [
                ['account_id' => '10-03-4000', 'debit_amount' => 100, 'credit_amount' => 0],
                ['account_id' => '10-03-3000', 'debit_amount' => 0, 'credit_amount' => 100],
            ],
        ]);
        
        $transaction = $this->service->createManualGlTransaction($dto);
        $this->service->postTransaction($transaction);
        
        // Try to update
        $transaction->transaction_description = 'Modified description';
        
        $this->expectException(\Exception::class);
        $transaction->save();
    }
    
    #[Test]
    public function it_calculates_totals_correctly()
    {
        $dto = new CreateGlTransactionDto([
            'fiscal_date' => '2025-06-15',
            'transaction_description' => 'Multi-line entry',
            'lines' => [
                ['account_id' => '10-03-4000', 'debit_amount' => 500, 'credit_amount' => 0],
                ['account_id' => '10-03-4001', 'debit_amount' => 300, 'credit_amount' => 0],
                ['account_id' => '10-03-1105', 'debit_amount' => 200, 'credit_amount' => 0],
                ['account_id' => '10-03-3000', 'debit_amount' => 0, 'credit_amount' => 1000],
            ],
        ]);
        
        $transaction = $this->service->createManualGlTransaction($dto);
        
        $this->assertEquals(1000.00, $transaction->total_debits->toFloat());
        $this->assertEquals(1000.00, $transaction->total_credits->toFloat());
        $this->assertTrue($transaction->is_balanced);
    }
    
    #[Test]
    public function it_handles_different_transaction_types()
    {
        // Create transactions of different types
        $types = [
            GlTransactionHeader::TYPE_MANUAL_GL => 'GL',
            GlTransactionHeader::TYPE_BANK => 'BNK',
            GlTransactionHeader::TYPE_RECEIVABLE => 'RM',
            GlTransactionHeader::TYPE_PAYABLE => 'PM',
        ];
        
        foreach ($types as $type => $expectedModule) {
            $period = FiscalPeriod::getPeriodFromDate(Carbon::parse('2025-06-15'));
            
            // Close period for specific module
            $column = 'is_open_' . strtolower($expectedModule);
            $period->update([$column => false]);
            
            // Try to create transaction
            $transaction = new GlTransactionHeader([
                'fiscal_date' => '2025-06-15',
                'gl_transaction_type' => $type,
                'team_id' => $this->team->id,
            ]);
            
            $this->expectException(\Exception::class);
            $transaction->save();
            
            // Re-open period
            $period->update([$column => true]);
        }
    }
    
    protected function setupTestEnvironment()
    {
        // Setup team
        $this->team = \App\Models\Team::factory()->create();
        $this->actingAs(\App\Models\User::factory()->create());
        setCurrentTeamId($this->team->id);
        
        // Setup fiscal year
        FiscalYearSetup::create([
            'team_id' => $this->team->id,
            'fiscal_start_date' => '2024-05-01',
        ]);
        
        // Setup account segments and accounts
        AccountSegmentService::setupDefaultSegmentStructure();
        AccountSegmentService::setupSampleSegmentValues();
        
        // Create test accounts
        $accountTypes = [
            '4000' => 'asset',   // Cash
            '4001' => 'asset',   // Bank
            '1105' => 'expense', // Material expense
            '3000' => 'equity',  // Owner equity
        ];
        
        foreach ($accountTypes as $naturalAccount => $type) {
            AccountSegmentService::createAccount(
                [1 => '10', 2 => '03', 3 => $naturalAccount],
                [
                    'account_type' => $type,
                    'is_active' => true,
                    'allow_manual_entry' => true,
                    'team_id' => $this->team->id,
                ]
            );
        }
    }
}

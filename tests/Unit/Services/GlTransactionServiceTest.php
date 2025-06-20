<?php

namespace Condoedge\Finance\Tests\Unit\Services;

use Condoedge\Finance\Tests\TestCase;
use Condoedge\Finance\Services\GlTransactionService;
use Condoedge\Finance\Services\AccountSegmentService;
use Condoedge\Finance\Models\GlTransactionHeader;
use Condoedge\Finance\Models\GlTransactionLine;
use Condoedge\Finance\Models\GlAccount;
use Condoedge\Finance\Models\FiscalYearSetup;
use Condoedge\Finance\Models\FiscalPeriod;
use Condoedge\Finance\Models\Dto\CreateGlTransactionDto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class GlTransactionServiceTest extends TestCase
{
    use RefreshDatabase;
    
    protected GlTransactionService $service;
    protected AccountSegmentService $segmentService;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(GlTransactionService::class);
        $this->segmentService = app(AccountSegmentService::class);
        
        // Setup account segments and sample accounts
        $this->setupTestEnvironment();
    }
    
    protected function setupTestEnvironment()
    {
        // Setup segments
        $this->segmentService->setupDefaultSegmentStructure();
        $this->segmentService->setupSampleSegmentValues();
        
        // Create test accounts
        $this->segmentService->createAccount([1 => '10', 2 => '03', 3 => '4000'], [
            'account_description' => 'Cash',
            'account_type' => 'asset',
            'team_id' => 1,
        ]);
        
        $this->segmentService->createAccount([1 => '10', 2 => '03', 3 => '2000'], [
            'account_description' => 'Accounts Payable',
            'account_type' => 'liability',
            'team_id' => 1,
        ]);
        
        // Setup fiscal year
        FiscalYearSetup::create([
            'team_id' => 1,
            'fiscal_start_date' => Carbon::parse('2024-01-01'),
        ]);
        
        // Create fiscal period
        FiscalPeriod::create([
            'team_id' => 1,
            'fiscal_year' => 2024,
            'period_id' => 202401,
            'period_name' => 'January 2024',
            'start_date' => Carbon::parse('2024-01-01'),
            'end_date' => Carbon::parse('2024-01-31'),
            'is_open_gl' => true,
            'is_open_bnk' => true,
            'is_open_rm' => true,
            'is_open_pm' => true,
        ]);
    }
    
    #[Test]
    public function it_can_create_balanced_gl_transaction()
    {
        $dto = new CreateGlTransactionDto([
            'fiscal_date' => '2024-01-15',
            'transaction_description' => 'Test transaction',
            'gl_transaction_type' => GlTransactionHeader::TYPE_MANUAL_GL,
            'team_id' => 1,
            'lines' => [
                [
                    'account_id' => '10-03-4000',
                    'line_description' => 'Cash increase',
                    'debit_amount' => 1000.00,
                    'credit_amount' => 0,
                ],
                [
                    'account_id' => '10-03-2000',
                    'line_description' => 'Payable increase',
                    'debit_amount' => 0,
                    'credit_amount' => 1000.00,
                ],
            ],
        ]);
        
        $transaction = $this->service->createTransaction($dto);
        
        $this->assertInstanceOf(GlTransactionHeader::class, $transaction);
        $this->assertEquals('2024-01-15', $transaction->fiscal_date->format('Y-m-d'));
        $this->assertEquals('Test transaction', $transaction->transaction_description);
        $this->assertEquals(2024, $transaction->fiscal_year);
        $this->assertEquals(202401, $transaction->fiscal_period);
        $this->assertTrue($transaction->is_balanced);
        $this->assertFalse($transaction->is_posted);
        
        // Check lines
        $this->assertCount(2, $transaction->lines);
        $debitLine = $transaction->lines->firstWhere('account_id', '10-03-4000');
        $this->assertEquals(1000.00, $debitLine->debit_amount);
        $this->assertEquals(0, $debitLine->credit_amount);
    }
    
    #[Test]
    public function it_prevents_creating_unbalanced_transaction()
    {
        $dto = new CreateGlTransactionDto([
            'fiscal_date' => '2024-01-15',
            'transaction_description' => 'Unbalanced transaction',
            'gl_transaction_type' => GlTransactionHeader::TYPE_MANUAL_GL,
            'team_id' => 1,
            'lines' => [
                [
                    'account_id' => '10-03-4000',
                    'debit_amount' => 1000.00,
                    'credit_amount' => 0,
                ],
                [
                    'account_id' => '10-03-2000',
                    'debit_amount' => 0,
                    'credit_amount' => 900.00, // Unbalanced!
                ],
            ],
        ]);
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Transaction must balance');
        $this->service->createTransaction($dto);
    }
    
    #[Test]
    public function it_prevents_posting_to_closed_period()
    {
        // Close the period
        FiscalPeriod::where('period_id', 202401)->update(['is_open_gl' => false]);
        
        $dto = new CreateGlTransactionDto([
            'fiscal_date' => '2024-01-15',
            'transaction_description' => 'Transaction in closed period',
            'gl_transaction_type' => GlTransactionHeader::TYPE_MANUAL_GL,
            'team_id' => 1,
            'lines' => [
                [
                    'account_id' => '10-03-4000',
                    'debit_amount' => 1000.00,
                    'credit_amount' => 0,
                ],
                [
                    'account_id' => '10-03-2000',
                    'debit_amount' => 0,
                    'credit_amount' => 1000.00,
                ],
            ],
        ]);
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('closed');
        $this->service->createTransaction($dto);
    }
    
    #[Test]
    public function it_can_post_balanced_transaction()
    {
        $dto = new CreateGlTransactionDto([
            'fiscal_date' => '2024-01-15',
            'transaction_description' => 'Transaction to post',
            'gl_transaction_type' => GlTransactionHeader::TYPE_MANUAL_GL,
            'team_id' => 1,
            'lines' => [
                [
                    'account_id' => '10-03-4000',
                    'debit_amount' => 500.00,
                    'credit_amount' => 0,
                ],
                [
                    'account_id' => '10-03-2000',
                    'debit_amount' => 0,
                    'credit_amount' => 500.00,
                ],
            ],
        ]);
        
        $transaction = $this->service->createTransaction($dto);
        $this->assertFalse($transaction->is_posted);
        
        $this->service->postTransaction($transaction);
        
        $transaction->refresh();
        $this->assertTrue($transaction->is_posted);
    }
    
    #[Test]
    public function it_prevents_posting_unbalanced_transaction()
    {
        // Create transaction directly to bypass validation
        $transaction = GlTransactionHeader::create([
            'gl_transaction_id' => '2024-01-000001',
            'gl_transaction_number' => 1,
            'fiscal_date' => '2024-01-15',
            'fiscal_year' => 2024,
            'fiscal_period' => 202401,
            'gl_transaction_type' => GlTransactionHeader::TYPE_MANUAL_GL,
            'transaction_description' => 'Unbalanced',
            'team_id' => 1,
            'is_balanced' => false,
            'is_posted' => false,
        ]);
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot post unbalanced transaction');
        $this->service->postTransaction($transaction);
    }
    
    #[Test]
    public function it_prevents_modifying_posted_transaction()
    {
        $dto = new CreateGlTransactionDto([
            'fiscal_date' => '2024-01-15',
            'transaction_description' => 'Posted transaction',
            'gl_transaction_type' => GlTransactionHeader::TYPE_MANUAL_GL,
            'team_id' => 1,
            'lines' => [
                [
                    'account_id' => '10-03-4000',
                    'debit_amount' => 100.00,
                    'credit_amount' => 0,
                ],
                [
                    'account_id' => '10-03-2000',
                    'debit_amount' => 0,
                    'credit_amount' => 100.00,
                ],
            ],
        ]);
        
        $transaction = $this->service->createTransaction($dto);
        $this->service->postTransaction($transaction);
        
        $this->assertFalse($transaction->canBeModified());
        
        // Try to update
        $this->expectException(\Exception::class);
        $updateDto = new CreateGlTransactionDto($dto->toArray());
        $updateDto->transaction_description = 'Modified description';
        $this->service->updateTransaction($transaction, $updateDto);
    }
    
    #[Test]
    public function it_generates_sequential_transaction_numbers()
    {
        $transactions = [];
        
        for ($i = 0; $i < 3; $i++) {
            $dto = new CreateGlTransactionDto([
                'fiscal_date' => '2024-01-15',
                'transaction_description' => "Transaction {$i}",
                'gl_transaction_type' => GlTransactionHeader::TYPE_MANUAL_GL,
                'team_id' => 1,
                'lines' => [
                    [
                        'account_id' => '10-03-4000',
                        'debit_amount' => 100.00,
                        'credit_amount' => 0,
                    ],
                    [
                        'account_id' => '10-03-2000',
                        'debit_amount' => 0,
                        'credit_amount' => 100.00,
                    ],
                ],
            ]);
            
            $transactions[] = $this->service->createTransaction($dto);
        }
        
        // Check sequential numbers
        $this->assertEquals(1, $transactions[0]->gl_transaction_number);
        $this->assertEquals(2, $transactions[1]->gl_transaction_number);
        $this->assertEquals(3, $transactions[2]->gl_transaction_number);
        
        // Check transaction IDs
        $this->assertEquals('2024-01-000001', $transactions[0]->gl_transaction_id);
        $this->assertEquals('2024-01-000002', $transactions[1]->gl_transaction_id);
        $this->assertEquals('2024-01-000003', $transactions[2]->gl_transaction_id);
    }
    
    #[Test]
    public function it_restricts_manual_entry_accounts()
    {
        // Create restricted account
        $this->segmentService->createAccount([1 => '10', 2 => '03', 3 => '9999'], [
            'account_description' => 'System Account',
            'account_type' => 'asset',
            'team_id' => 1,
            'allow_manual_entry' => false,
        ]);
        
        $dto = new CreateGlTransactionDto([
            'fiscal_date' => '2024-01-15',
            'transaction_description' => 'Using restricted account',
            'gl_transaction_type' => GlTransactionHeader::TYPE_MANUAL_GL,
            'team_id' => 1,
            'lines' => [
                [
                    'account_id' => '10-03-9999', // Restricted account
                    'debit_amount' => 100.00,
                    'credit_amount' => 0,
                ],
                [
                    'account_id' => '10-03-2000',
                    'debit_amount' => 0,
                    'credit_amount' => 100.00,
                ],
            ],
        ]);
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('restricted for manual entry');
        $this->service->createTransaction($dto);
    }
    
    #[Test]
    public function it_allows_system_transactions_to_use_restricted_accounts()
    {
        // Create restricted account
        $this->segmentService->createAccount([1 => '10', 2 => '03', 3 => '9999'], [
            'account_description' => 'System Account',
            'account_type' => 'asset',
            'team_id' => 1,
            'allow_manual_entry' => false,
        ]);
        
        $dto = new CreateGlTransactionDto([
            'fiscal_date' => '2024-01-15',
            'transaction_description' => 'Bank transaction',
            'gl_transaction_type' => GlTransactionHeader::TYPE_BANK, // System transaction
            'team_id' => 1,
            'originating_module_transaction_id' => 'BNK-001',
            'lines' => [
                [
                    'account_id' => '10-03-9999', // Restricted account - OK for system
                    'debit_amount' => 100.00,
                    'credit_amount' => 0,
                ],
                [
                    'account_id' => '10-03-2000',
                    'debit_amount' => 0,
                    'credit_amount' => 100.00,
                ],
            ],
        ]);
        
        $transaction = $this->service->createTransaction($dto);
        $this->assertEquals(GlTransactionHeader::TYPE_BANK, $transaction->gl_transaction_type);
        $this->assertEquals('BNK-001', $transaction->originating_module_transaction_id);
    }
}

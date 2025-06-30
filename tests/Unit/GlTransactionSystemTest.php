<?php

namespace Condoedge\Finance\Tests\Unit;

use Condoedge\Finance\Models\GlTransactionHeader;
use Condoedge\Finance\Models\GlAccount;
use Condoedge\Finance\Models\SegmentValue;
use Condoedge\Finance\Models\Dto\Gl\CreateGlTransactionDto;
use Condoedge\Finance\Services\GlTransactionServiceInterface;
use Condoedge\Finance\Tests\FinanceTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use WendellAdriel\ValidatedDTO\Exceptions\ValidatedDTOException;
use PHPUnit\Framework\Attributes\Test;

class GlTransactionSystemTest extends FinanceTestCase
{
    use RefreshDatabase;

    protected $glTransactionService;

    #[Test]
    public function it_creates_a_balanced_manual_gl_transaction()
    {
        // Arrange
        $this->setupTestEnvironment();
        
        // Create test accounts
        $cashAccount = $this->createTestAccount('1000', 'Cash', 'asset', true);
        $revenueAccount = $this->createTestAccount('4000', 'Revenue', 'revenue', true);
        
        // Prepare transaction data
        $dto = new CreateGlTransactionDto([
            'fiscal_date' => '2025-01-15',
            'gl_transaction_type' => 1, // Manual GL
            'transaction_description' => 'Test journal entry',
            'team_id' => $this->team->id,
            'lines' => [
                [
                    'account_id' => $cashAccount->account_id,
                    'line_description' => 'Debit cash',
                    'debit_amount' => 100.00,
                    'credit_amount' => 0
                ],
                [
                    'account_id' => $revenueAccount->account_id,
                    'line_description' => 'Credit revenue',
                    'debit_amount' => 0,
                    'credit_amount' => 100.00
                ]
            ]
        ]);
        
        // Act
        $transaction = $this->glTransactionService->createManualGlTransaction($dto);
        
        // Assert
        $this->assertInstanceOf(GlTransactionHeader::class, $transaction);
        $this->assertEquals('2025-01-15', $transaction->fiscal_date->format('Y-m-d'));
        $this->assertEquals(1, $transaction->gl_transaction_type);
        $this->assertEquals('Test journal entry', $transaction->transaction_description);
        $this->assertTrue($transaction->is_balanced);
        $this->assertFalse($transaction->is_posted);
        $this->assertCount(2, $transaction->lines);
        $this->assertStringStartsWith('2025-01-', $transaction->gl_transaction_id);
    }

    #[Test]
    public function it_prevents_creating_unbalanced_transaction()
    {
        // Arrange
        $this->setupTestEnvironment();
        
        $cashAccount = $this->createTestAccount('1000', 'Cash', 'asset', true);
        $revenueAccount = $this->createTestAccount('4000', 'Revenue', 'revenue', true);
        
        $dto = new CreateGlTransactionDto([
            'fiscal_date' => '2025-01-15',
            'gl_transaction_type' => 1,
            'transaction_description' => 'Unbalanced transaction',
            'team_id' => $this->team->id,
            'lines' => [
                [
                    'account_id' => $cashAccount->account_id,
                    'line_description' => 'Debit cash',
                    'debit_amount' => 100.00,
                    'credit_amount' => 0
                ],
                [
                    'account_id' => $revenueAccount->account_id,
                    'line_description' => 'Credit revenue',
                    'debit_amount' => 0,
                    'credit_amount' => 50.00 // Unbalanced!
                ]
            ]
        ]);
        
        // Act & Assert
        $this->expectException(ValidatedDTOException::class);
        
        try {
            $this->glTransactionService->createManualGlTransaction($dto);
        } catch (ValidatedDTOException $e) {
            $errors = $e->getValidationErrors();
            $this->assertArrayHasKey('lines', $errors);
            $this->assertStringContainsString('must balance', $errors['lines'][0]);
            throw $e;
        }
    }

    #[Test]
    public function it_prevents_using_inactive_accounts()
    {
        // Arrange
        $this->setupTestEnvironment();
        
        $activeAccount = $this->createTestAccount('1000', 'Cash', 'asset', true);
        $inactiveAccount = $this->createTestAccount('4000', 'Old Revenue', 'revenue', true);
        $inactiveAccount->update(['is_active' => false]);
        
        $dto = new CreateGlTransactionDto([
            'fiscal_date' => '2025-01-15',
            'gl_transaction_type' => 1,
            'transaction_description' => 'Test with inactive account',
            'team_id' => $this->team->id,
            'lines' => [
                [
                    'account_id' => $activeAccount->account_id,
                    'line_description' => 'Debit cash',
                    'debit_amount' => 100.00,
                    'credit_amount' => 0
                ],
                [
                    'account_id' => $inactiveAccount->account_id,
                    'line_description' => 'Credit inactive account',
                    'debit_amount' => 0,
                    'credit_amount' => 100.00
                ]
            ]
        ]);
        
        // Act & Assert
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/inactive/');
        
        $this->glTransactionService->createManualGlTransaction($dto);
    }

    #[Test]
    public function it_prevents_manual_entry_on_restricted_accounts()
    {
        // Arrange
        $this->setupTestEnvironment();
        
        $manualAccount = $this->createTestAccount('1000', 'Cash', 'asset', true);
        $restrictedAccount = $this->createTestAccount('2000', 'System Account', 'liability', false);
        
        $dto = new CreateGlTransactionDto([
            'fiscal_date' => '2025-01-15',
            'gl_transaction_type' => 1,
            'transaction_description' => 'Test with restricted account',
            'team_id' => $this->team->id,
            'lines' => [
                [
                    'account_id' => $manualAccount->account_id,
                    'line_description' => 'Debit cash',
                    'debit_amount' => 100.00,
                    'credit_amount' => 0
                ],
                [
                    'account_id' => $restrictedAccount->account_id,
                    'line_description' => 'Credit restricted account',
                    'debit_amount' => 0,
                    'credit_amount' => 100.00
                ]
            ]
        ]);
        
        // Act & Assert
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/manual entry/');
        
        $this->glTransactionService->createManualGlTransaction($dto);
    }

    #[Test]
    public function it_posts_a_balanced_transaction()
    {
        // Arrange
        $this->setupTestEnvironment();
        
        $cashAccount = $this->createTestAccount('1000', 'Cash', 'asset', true);
        $revenueAccount = $this->createTestAccount('4000', 'Revenue', 'revenue', true);
        
        $dto = new CreateGlTransactionDto([
            'fiscal_date' => '2025-01-15',
            'gl_transaction_type' => 1,
            'transaction_description' => 'Transaction to post',
            'team_id' => $this->team->id,
            'lines' => [
                [
                    'account_id' => $cashAccount->account_id,
                    'line_description' => 'Debit cash',
                    'debit_amount' => 100.00,
                    'credit_amount' => 0
                ],
                [
                    'account_id' => $revenueAccount->account_id,
                    'line_description' => 'Credit revenue',
                    'debit_amount' => 0,
                    'credit_amount' => 100.00
                ]
            ]
        ]);
        
        $transaction = $this->glTransactionService->createManualGlTransaction($dto);
        
        // Act
        $postedTransaction = $this->glTransactionService->postTransaction($transaction);
        
        // Assert
        $this->assertTrue($postedTransaction->is_posted);
        $this->assertEquals($transaction->gl_transaction_id, $postedTransaction->gl_transaction_id);
    }

    #[Test]
    public function it_prevents_posting_unbalanced_transaction()
    {
        // Arrange
        $transaction = GlTransactionHeader::factory()->create([
            'is_balanced' => false,
            'is_posted' => false
        ]);
        
        // Act & Assert
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/[Cc]annot post unbalanced/');
        
        $this->glTransactionService->postTransaction($transaction);
    }

    #[Test]
    public function it_reverses_a_posted_transaction()
    {
        // Arrange
        $this->setupTestEnvironment();
        
        $cashAccount = $this->createTestAccount('1000', 'Cash', 'asset', true);
        $revenueAccount = $this->createTestAccount('4000', 'Revenue', 'revenue', true);
        
        // Create and post original transaction
        $dto = new CreateGlTransactionDto([
            'fiscal_date' => '2025-01-15',
            'gl_transaction_type' => 1,
            'transaction_description' => 'Original transaction',
            'team_id' => $this->team->id,
            'lines' => [
                [
                    'account_id' => $cashAccount->account_id,
                    'line_description' => 'Debit cash',
                    'debit_amount' => 100.00,
                    'credit_amount' => 0
                ],
                [
                    'account_id' => $revenueAccount->account_id,
                    'line_description' => 'Credit revenue',
                    'debit_amount' => 0,
                    'credit_amount' => 100.00
                ]
            ]
        ]);
        
        $originalTransaction = $this->glTransactionService->createManualGlTransaction($dto);
        $this->glTransactionService->postTransaction($originalTransaction);
        
        // Act
        $reversalTransaction = $this->glTransactionService->reverseTransaction(
            $originalTransaction->gl_transaction_id,
            'Correction of error'
        );
        
        // Assert
        $this->assertInstanceOf(GlTransactionHeader::class, $reversalTransaction);
        $this->assertTrue($reversalTransaction->is_posted);
        $this->assertEquals('Correction of error', $reversalTransaction->transaction_description);
        $this->assertEquals($originalTransaction->gl_transaction_id, $reversalTransaction->originating_module_transaction_id);
        
        // Check reversed amounts
        $reversalLines = $reversalTransaction->lines;
        $this->assertCount(2, $reversalLines);
        
        $cashLine = $reversalLines->firstWhere('account_id', $cashAccount->account_id);
        $this->assertEquals(0, $cashLine->debit_amount);
        $this->assertEquals(100.00, $cashLine->credit_amount); // Reversed
        
        $revenueLine = $reversalLines->firstWhere('account_id', $revenueAccount->account_id);
        $this->assertEquals(100.00, $revenueLine->debit_amount); // Reversed
        $this->assertEquals(0, $revenueLine->credit_amount);
    }

    #[Test]
    public function it_generates_sequential_transaction_numbers_without_gaps()
    {
        // Arrange
        $this->setupTestEnvironment();
        
        $cashAccount = $this->createTestAccount('1000', 'Cash', 'asset', true);
        $revenueAccount = $this->createTestAccount('4000', 'Revenue', 'revenue', true);
        
        $numbers = [];
        
        // Act - Create 5 transactions
        for ($i = 0; $i < 5; $i++) {
            $dto = new CreateGlTransactionDto([
                'fiscal_date' => '2025-01-15',
                'gl_transaction_type' => 1,
                'transaction_description' => "Transaction {$i}",
                'team_id' => $this->team->id,
                'lines' => [
                    [
                        'account_id' => $cashAccount->account_id,
                        'line_description' => 'Debit',
                        'debit_amount' => 10.00,
                        'credit_amount' => 0
                    ],
                    [
                        'account_id' => $revenueAccount->account_id,
                        'line_description' => 'Credit',
                        'debit_amount' => 0,
                        'credit_amount' => 10.00
                    ]
                ]
            ]);
            
            $transaction = $this->glTransactionService->createManualGlTransaction($dto);
            $numbers[] = $transaction->gl_transaction_number;
        }
        
        // Assert - Numbers should be sequential without gaps
        $this->assertEquals([1, 2, 3, 4, 5], $numbers);
    }

    /**
     * Helper method to set up test environment
     */
    protected function setupTestEnvironment()
    {
        parent::setUp();
        
        $this->glTransactionService = app(GlTransactionServiceInterface::class);
        
        // Set current team ID
        $this->actingAs($this->user);
        setCurrentTeamId($this->team->id);
    }

    /**
     * Helper method to create test account
     */
    protected function createTestAccount($accountCode, $description, $type, $allowManualEntry)
    {
        // This would depend on your account creation logic
        // For now, assuming direct creation
        return GlAccount::create([
            'account_id' => "10-01-{$accountCode}",
            'account_segments_descriptor' => "Test - Department - {$description}",
            'account_type' => $type,
            'is_active' => true,
            'allow_manual_entry' => $allowManualEntry,
            'team_id' => $this->team->id
        ]);
    }
}

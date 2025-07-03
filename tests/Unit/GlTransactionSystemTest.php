<?php

namespace Tests\Unit;

use Condoedge\Finance\Casts\SafeDecimal;
use Condoedge\Finance\Models\Dto\Gl\CreateSegmentValueDto;
use Condoedge\Finance\Models\GlAccount;
use Condoedge\Finance\Models\Dto\Gl\CreateGlTransactionDto;
use Condoedge\Finance\Models\Dto\Gl\CreateGlTransactionLineDto;
use Condoedge\Finance\Models\FiscalPeriod;
use Condoedge\Finance\Models\FiscalYearSetup;
use Condoedge\Finance\Models\GlTransactionHeader;
use Condoedge\Finance\Models\GlTransactionLine;
use Condoedge\Finance\Enums\GlTransactionTypeEnum;
use Condoedge\Finance\Models\AccountTypeEnum;
use Condoedge\Finance\Models\Dto\Gl\CreateAccountDto;
use Condoedge\Finance\Services\AccountSegmentService;
use Condoedge\Finance\Services\FiscalYearService;
use Condoedge\Finance\Services\GlTransactionService;
use Exception;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Kompo\Auth\Database\Factories\UserFactory;
use Tests\TestCase;

class GlTransactionSystemTest extends TestCase
{
    use WithFaker;

    protected GlTransactionService $glService;
    protected FiscalYearService $fiscalService;
    protected AccountSegmentService $accountService;

    public function setUp(): void
    {
        parent::setUp();

        /** @var \Kompo\Auth\Models\User $user */
        $user = UserFactory::new()->create()->first();
        if (!$user) throw new Exception('Unknown error creating user');
        $this->actingAs($user);

        $this->glService = app(GlTransactionService::class);
        $this->fiscalService = app(FiscalYearService::class);
        $this->accountService = app(AccountSegmentService::class);
        
        // Setup test environment
        $this->setupTestEnvironment();
    }

    /**
     * Test that sequential numbers are generated without gaps
     */
    public function test_it_generates_sequential_numbers_without_gaps()
    {
        $transactions = [];
        
        // Create 5 transactions
        for ($i = 1; $i <= 5; $i++) {
            $dto = $this->createBalancedTransactionDto(100 * $i);
            $transaction = $this->glService->createTransaction($dto);
            $transactions[] = $transaction;
        }

        // Verify sequential numbering
        for ($i = 0; $i < count($transactions); $i++) {
            $expectedNumber = $i + 1;
            $this->assertEquals($expectedNumber, $transactions[$i]->gl_transaction_number);
        }

        // Verify no gaps in database
        $numbers = GlTransactionHeader::orderBy('gl_transaction_number')
            ->pluck('gl_transaction_number')
            ->toArray();
        
        $this->assertEquals(range(1, 5), $numbers);
    }

    /**
     * Test that unbalanced transactions are prevented
     */
    public function test_it_prevents_unbalanced_transactions()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage(__('validation-with-values-transaction-must-balance'));

        $dto = new CreateGlTransactionDto([
            'fiscal_date' => now()->format('Y-m-d'),
            'transaction_description' => 'Unbalanced transaction',
            'gl_transaction_type' => GlTransactionTypeEnum::MANUAL_GL,
            'team_id' => currentTeamId(),
            'lines' => [
                [
                    'account_id' => $this->getTestAccountId(AccountTypeEnum::ASSET),
                    'line_description' => 'Debit line',
                    'debit_amount' => 100,
                    'credit_amount' => 0,
                ],
                [
                    'account_id' => $this->getTestAccountId(AccountTypeEnum::REVENUE),
                    'line_description' => 'Credit line - wrong amount',
                    'debit_amount' => 0,
                    'credit_amount' => 90, // Should be 100 to balance
                ],
            ],
        ]);

        $this->glService->createTransaction($dto);
    }

    /**
     * Test that fiscal period validation works
     */
    public function test_it_validates_fiscal_period_is_open()
    {
        // Close the current period for GL
        $currentPeriod = FiscalPeriod::getPeriodFromDate(now(), currentTeamId());
        $currentPeriod->is_open_gl = false;
        $currentPeriod->save();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(__('finance-fiscal-year-period-closed'));

        $dto = $this->createBalancedTransactionDto(500);
        $this->glService->createTransaction($dto);
    }

    /**
     * Test that posted transactions cannot be modified
     */
    public function test_it_prevents_modification_of_posted_transactions()
    {
        // Create and post a transaction
        $dto = $this->createBalancedTransactionDto(1000);
        $transaction = $this->glService->createTransaction($dto);

        // Post the transaction
        $this->glService->postTransaction($transaction);
        $transaction->refresh();
        
        $this->assertTrue($transaction->is_posted);

        // Try to modify a posted transaction
        $this->expectException(Exception::class);
        $this->expectExceptionMessage(__('translate.error-cannot-modify-posted-transaction'));

        // Try to update description
        $transaction->transaction_description = 'Modified description';
        $transaction->save(); // Trigger should prevent this
    }

    /**
     * Test transaction reversal functionality
     */
    public function test_it_handles_reversal_correctly()
    {
        // Create original transaction
        $originalDto = $this->createBalancedTransactionDto(750);
        $original = $this->glService->createTransaction($originalDto);
        
        // Post it
        $this->glService->postTransaction($original);

        // Create reversal
        $reversal = $this->glService->reverseTransaction($original->id);

        // Verify reversal properties
        $this->assertStringContainsString('Reversal of', $reversal->transaction_description);
        $this->assertEquals($original->gl_transaction_id, $reversal->originating_module_transaction_id);
        $this->assertTrue($reversal->is_posted);

        // Verify lines are reversed
        $originalLines = $original->lines()->get();
        $reversalLines = $reversal->lines()->get();

        $this->assertCount($originalLines->count(), $reversalLines);

        foreach ($originalLines as $index => $originalLine) {
            $reversalLine = $reversalLines[$index];
            
            // Debits and credits should be swapped
            $this->assertEqualsDecimals($originalLine->debit_amount, $reversalLine->credit_amount);
            $this->assertEqualsDecimals($originalLine->credit_amount, $reversalLine->debit_amount);
            $this->assertEquals($originalLine->account_id, $reversalLine->account_id);
        }
    }

    /**
     * Test that trial balance calculates accurately
     */
    public function test_it_calculates_trial_balance_accurately()
    {
        // Create several transactions
        $transactions = [
            // Cash sale
            $this->createAndPostTransaction([
                ['account' => AccountTypeEnum::ASSET, 'debit' => 1000, 'credit' => 0],
                ['account' => AccountTypeEnum::REVENUE, 'debit' => 0, 'credit' => 1000],
            ]),
            // Expense payment
            $this->createAndPostTransaction([
                ['account' => AccountTypeEnum::EXPENSE, 'debit' => 300, 'credit' => 0],
                ['account' => AccountTypeEnum::ASSET, 'debit' => 0, 'credit' => 300],
            ]),
            // Owner investment
            $this->createAndPostTransaction([
                ['account' => AccountTypeEnum::ASSET, 'debit' => 5000, 'credit' => 0],
                ['account' => AccountTypeEnum::EQUITY, 'debit' => 0, 'credit' => 5000],
            ]),
        ];

        // Calculate trial balance
        $trialBalance = $this->glService->getTrialBalance(
            now()->startOfMonth(),
            now()->endOfMonth(),
            true // Posted only
        );

        // Find specific accounts in trial balance
        $assetAccount = collect($trialBalance)->firstWhere('account_type', AccountTypeEnum::ASSET);
        $revenueAccount = collect($trialBalance)->firstWhere('account_type', AccountTypeEnum::REVENUE);
        $expenseAccount = collect($trialBalance)->firstWhere('account_type', AccountTypeEnum::EXPENSE);
        $equityAccount = collect($trialBalance)->firstWhere('account_type', AccountTypeEnum::EQUITY);

        // Verify balances
        // Asset: 1000 - 300 + 5000 = 5700 (debit balance)
        $this->assertEqualsDecimals(5700, $assetAccount['balance']);
        
        // Revenue: 1000 (credit balance, shown as negative)
        $this->assertEqualsDecimals(-1000, $revenueAccount['balance']);
        
        // Expense: 300 (debit balance)
        $this->assertEqualsDecimals(300, $expenseAccount['balance']);
        
        // Equity: 5000 (credit balance, shown as negative)
        $this->assertEqualsDecimals(-5000, $equityAccount['balance']);
    }

    /**
     * Test that transaction types map to correct modules
     */
    public function test_it_maps_transaction_types_to_modules_correctly()
    {
        $types = [
            GlTransactionTypeEnum::MANUAL_GL->value => 'is_open_gl',
            GlTransactionTypeEnum::BANK->value => 'is_open_bnk',
            GlTransactionTypeEnum::RECEIVABLE->value => 'is_open_rm',
            GlTransactionTypeEnum::PAYABLE->value => 'is_open_pm',
        ];

        foreach ($types as $type => $field) {
            $type = GlTransactionTypeEnum::from($type);
            // Get current period and close specific module
            $period = FiscalPeriod::getPeriodFromDate(now(), currentTeamId());
            
            // Open all modules first
            $period->is_open_gl = true;
            $period->is_open_bnk = true;
            $period->is_open_rm = true;
            $period->is_open_pm = true;
            $period->save();
            
            // Close only the specific module
            $period->$field = false;
            $period->save();

            // Try to create transaction of that type
            try {
                $this->glService->createTransaction(
                    $this->createBalancedTransactionDto(100, null, $type)
                );

                $this->fail("Expected exception for closed {$type->label()} module");
            } catch (Exception $e) {
                $this->assertStringContainsString(__('finance-fiscal-year-period-closed'), $e->getMessage());
            }
            
            // Reopen module for next test
            $period->$field = true;
            $period->save();
        }
    }

    /**
     * Test that only one amount (debit or credit) can be set per line
     */
    public function test_it_enforces_single_amount_per_line()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage(__('error-line-must-have-either-debit-or-credit'));

        $this->glService->createTransaction(new CreateGlTransactionDto([
            'fiscal_date' => now()->format('Y-m-d'),
            'gl_transaction_type' => GlTransactionTypeEnum::MANUAL_GL->value,
            'transaction_description' => 'Test manual GL transaction',
            'team_id' => currentTeamId(),
            'lines' => [
                [
                    'account_id' => $this->getTestAccountId(AccountTypeEnum::ASSET),
                    'line_description' => 'Test line',
                    'debit_amount' => 100,
                    'credit_amount' => 50, // Both amounts set
                ],
                [
                    'account_id' => $this->getTestAccountId(AccountTypeEnum::REVENUE),
                    'line_description' => 'Credit line',
                    'debit_amount' => 0,
                    'credit_amount' => 50,
                ],
            ],
        ]));
    }

    /**
     * Test precision handling with SafeDecimal
     */
    public function test_it_handles_decimal_precision_correctly()
    {
        $dto = new CreateGlTransactionDto([
            'fiscal_date' => now()->format('Y-m-d'),
            'transaction_description' => 'Precision test',
            'gl_transaction_type' => GlTransactionTypeEnum::MANUAL_GL->value,
            'team_id' => currentTeamId(),
            'lines' => [
                [
                    'account_id' => $this->getTestAccountId(AccountTypeEnum::ASSET),
                    'line_description' => 'Precise debit',
                    'debit_amount' => 123.45678, // Extra precision
                    'credit_amount' => 0,
                ],
                [
                    'account_id' => $this->getTestAccountId(AccountTypeEnum::REVENUE),
                    'line_description' => 'Precise credit',
                    'debit_amount' => 0,
                    'credit_amount' => 123.45678,
                ],
            ],
        ]);

        $transaction = $this->glService->createTransaction($dto);

        // Verify amounts are stored with correct precision (5 decimals)
        $lines = $transaction->lines;
        $this->assertEquals('123.45678', (string) $lines[0]->debit_amount);
        $this->assertEquals('123.45678', (string) $lines[1]->credit_amount);

        // Verify transaction is balanced
        $this->assertTrue($transaction->is_balanced);
        $this->assertEqualsDecimals($transaction->total_debits, $transaction->total_credits);

        // Failing if it is a minor decimal change

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(__('validation-with-values-transaction-must-balance'));

        $dto = new CreateGlTransactionDto([
            'fiscal_date' => now()->format('Y-m-d'),
            'transaction_description' => 'Precision test',
            'gl_transaction_type' => GlTransactionTypeEnum::MANUAL_GL->value,
            'team_id' => currentTeamId(),
            'lines' => [
                [
                    'account_id' => $this->getTestAccountId(AccountTypeEnum::ASSET),
                    'line_description' => 'Precise debit',
                    'debit_amount' => 123.45678, // Extra precision
                    'credit_amount' => 0,
                ],
                [
                    'account_id' => $this->getTestAccountId(AccountTypeEnum::REVENUE),
                    'line_description' => 'Precise credit',
                    'debit_amount' => 0,
                    'credit_amount' => 123.45677, // Minor change
                ],
            ],
        ]);
    }

    /**
     * Test that inactive accounts cannot be used
     */
    public function test_it_prevents_using_inactive_accounts()
    {
        // Create an inactive account
        $inactiveAccount = $this->createTestAccount(AccountTypeEnum::ASSET, false);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(__('translate.error-account-inactive'));

        $dto = new CreateGlTransactionDto([
            'fiscal_date' => now()->format('Y-m-d'),
            'transaction_description' => 'Transaction with inactive account',
            'team_id' => currentTeamId(),
            'gl_transaction_type' => GlTransactionTypeEnum::MANUAL_GL->value,
            'lines' => [
                [
                    'account_id' => $inactiveAccount->id,
                    'line_description' => 'Using inactive account',
                    'debit_amount' => 100,
                    'credit_amount' => 0,
                ],
                [
                    'account_id' => $this->getTestAccountId(AccountTypeEnum::REVENUE),
                    'line_description' => 'Credit line',
                    'debit_amount' => 0,
                    'credit_amount' => 100,
                ],
            ],
        ]);

        $this->glService->createTransaction($dto);
    }

    /**
     * Test that accounts with manual entry disabled cannot be used
     */
    public function test_it_prevents_manual_entry_on_restricted_accounts()
    {
        // Create account with manual entry disabled
        $restrictedAccount = $this->createTestAccount(AccountTypeEnum::ASSET, true, false);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(__('error-account-not-allow-manual-entry'));

        $dto = new CreateGlTransactionDto([
            'fiscal_date' => now()->format('Y-m-d'),
            'transaction_description' => 'Transaction with restricted account',
            'gl_transaction_type' => GlTransactionTypeEnum::MANUAL_GL->value,
            'team_id' => currentTeamId(),
            'lines' => [
                [
                    'account_id' => $restrictedAccount->id,
                    'line_description' => 'Using restricted account',
                    'debit_amount' => 100,
                    'credit_amount' => 0,
                ],
                [
                    'account_id' => $this->getTestAccountId(AccountTypeEnum::REVENUE),
                    'line_description' => 'Credit line',
                    'debit_amount' => 0,
                    'credit_amount' => 100,
                ],
            ],
        ]);

        $this->glService->createTransaction($dto);
    }

    /**
     * Test database trigger updates balance status
     */
    public function test_database_trigger_updates_balance_status()
    {
        $transactionId = 100;

        // Create transaction manually to test trigger        
        DB::table('fin_gl_transaction_headers')->insert([
            'id' => $transactionId,
            'gl_transaction_number' => 999999,
            'fiscal_date' => now()->format('Y-m-d'),
            'fiscal_period_id' => FiscalPeriod::getPeriodFromDate(now(), currentTeamId())->id,
            'gl_transaction_type' => GlTransactionTypeEnum::MANUAL_GL->value,
            'transaction_description' => 'Trigger test',
            'is_balanced' => false, // Start as unbalanced
            'is_posted' => false,
            'team_id' => currentTeamId(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Add unbalanced lines
        DB::table('fin_gl_transaction_lines')->insert([
            [
                'gl_transaction_id' => $transactionId,
                'account_id' => $this->getTestAccountId(AccountTypeEnum::ASSET),
                'line_description' => 'Debit',
                'debit_amount' => 100,
                'credit_amount' => 0,
                'created_at' => now(),
                'updated_at' => now(),
                'team_id' => currentTeamId(),
            ],
        ]);

        // Check that it's marked as unbalanced
        $header = GlTransactionHeader::find($transactionId);
        $this->assertFalse($header->is_balanced);

        // Add balancing line
        DB::table('fin_gl_transaction_lines')->insert([
            'gl_transaction_id' => $transactionId,
            'account_id' => $this->getTestAccountId(AccountTypeEnum::REVENUE),
            'line_description' => 'Credit',
            'debit_amount' => 0,
            'credit_amount' => 100,
            'created_at' => now(),
            'updated_at' => now(),
            'team_id' => currentTeamId(),
        ]);

        // Trigger should update balance status
        $header->refresh();
        $this->assertTrue($header->is_balanced);
    }

    /**
     * Test complex multi-currency or multi-line scenarios
     */
    public function test_it_handles_complex_multi_line_transactions()
    {
        // Create a complex journal entry (e.g., payroll)
        $dto = new CreateGlTransactionDto([
            'fiscal_date' => now()->format('Y-m-d'),
            'transaction_description' => 'Monthly payroll entry',
            'gl_transaction_type' => GlTransactionTypeEnum::MANUAL_GL->value,
            'team_id' => currentTeamId(),
            'lines' => [
                // Salary expense
                [
                    'account_id' => $this->getTestAccountId(AccountTypeEnum::EXPENSE),
                    'line_description' => 'Gross salaries',
                    'debit_amount' => 10000,
                    'credit_amount' => 0,
                ],
                // Tax withholding
                [
                    'account_id' => $this->getTestAccountId(AccountTypeEnum::LIABILITY),
                    'line_description' => 'Income tax payable',
                    'debit_amount' => 0,
                    'credit_amount' => 2000,
                ],
                // Social security
                [
                    'account_id' => $this->getTestAccountId(AccountTypeEnum::LIABILITY),
                    'line_description' => 'Social security payable',
                    'debit_amount' => 0,
                    'credit_amount' => 1000,
                ],
                // Net pay
                [
                    'account_id' => $this->getTestAccountId(AccountTypeEnum::LIABILITY),
                    'line_description' => 'Net salaries payable',
                    'debit_amount' => 0,
                    'credit_amount' => 7000,
                ],
            ],
        ]);

        $transaction = $this->glService->createTransaction($dto);

        // Verify transaction
        $this->assertTrue($transaction->is_balanced);
        $this->assertCount(4, $transaction->lines);
        $this->assertEqualsDecimals(10000, $transaction->total_debits);
        $this->assertEqualsDecimals(10000, $transaction->total_credits);
    }

    /**
     * Test that fiscal year is calculated correctly from fiscal date
     */
    public function test_it_calculates_fiscal_year_correctly()
    {
        $firstDate = '2025-01-15';

        // First setting the periods as opened
        $firstPeriod = $this->fiscalService->getOrCreatePeriodForDate(currentTeamId(), carbon($firstDate));
        $this->fiscalService->openPeriod($firstPeriod->id, GlTransactionTypeEnum::cases());

        // Test with date in second half of fiscal year
        $dto = $this->createBalancedTransactionDto(500, $firstDate);
        $transaction = $this->glService->createTransaction($dto);

        $this->assertEquals(2025, $transaction->fiscalPeriod->fiscal_year);

        $secondDate = '2024-05-15';
        // First setting the periods as opened
        $secondPeriod = $this->fiscalService->getOrCreatePeriodForDate(currentTeamId(), carbon($secondDate));
        $this->fiscalService->openPeriod($secondPeriod->id, GlTransactionTypeEnum::cases());

        // Test with date in first half of fiscal year (May)
        $dto2 = $this->createBalancedTransactionDto(500, $secondDate);
        $transaction2 = $this->glService->createTransaction($dto2);
        
        $this->assertEquals(2025, $transaction2->fiscalPeriod->fiscal_year); // FY 2025 starts May 2024
    }

    // Helper Methods

    private function setupTestEnvironment(): void
    {
        // Setup fiscal year (May 1 to April 30)
        $this->fiscalService->setupFiscalYear(currentTeamId(), Carbon::parse('2024-05-01'));

        // Setup account segments
        $this->accountService->createDefaultSegments();
        
        // Create test accounts for each type
        $this->createTestAccounts();
    }

    private function createTestAccounts(): void
    {
        $accountTypes = AccountTypeEnum::cases();
        
        foreach ($accountTypes as $type) {
            $this->createTestAccount($type);
        }
    }

    private function createTestAccount(AccountTypeEnum $type, bool $isActive = true, bool $allowManualEntry = true): GlAccount
    {
        // Get or create segment values
        $segments = $this->accountService->getSegmentStructure();
        $segmentValueIds = [];

        foreach ($segments as $segment) {
            // Create segment value for each segment
            $segmentValue = $this->accountService->createSegmentValue(new CreateSegmentValueDto([
                'segment_definition_id' => $segment->id,
                'segment_value' => Str::random($segment->segment_length),
                'segment_description' => ucfirst($type->value) . ' Segment',
                'is_active' => true,
                'account_type' => $type->value,
            ]));
            $segmentValueIds[] = $segmentValue->id;
        }

        // Create account
        return $this->accountService->createAccount(new CreateAccountDto([
            'segment_value_ids' => $segmentValueIds,
            'is_active' => $isActive,
            'allow_manual_entry' => $allowManualEntry,
        ]));
    }

    private function getTestAccountId(AccountTypeEnum $type): int
    {
        $account = GlAccount::whereHas('lastSegmentValue', fn($q) => $q->where('account_type', $type))
            ->where('is_active', true)
            ->where('allow_manual_entry', true)
            ->first();
            
        if (!$account) {
            $account = $this->createTestAccount($type);
        }
        
        return $account->id;
    }

    private function createBalancedTransactionDto(float $amount, string $date = null, $transactionType = GlTransactionTypeEnum::MANUAL_GL): CreateGlTransactionDto
    {
        return new CreateGlTransactionDto([
            'fiscal_date' => $date ?? now()->format('Y-m-d'),
            'transaction_description' => 'Test transaction for $' . $amount,
            'gl_transaction_type' => $transactionType->value,
            'team_id' => currentTeamId(),
            'lines' => [
                [
                    'account_id' => $this->getTestAccountId(AccountTypeEnum::ASSET),
                    'line_description' => 'Debit line',
                    'debit_amount' => $amount,
                    'credit_amount' => 0,
                ],
                [
                    'account_id' => $this->getTestAccountId(AccountTypeEnum::REVENUE),
                    'line_description' => 'Credit line',
                    'debit_amount' => 0,
                    'credit_amount' => $amount,
                ],
            ],
        ]);
    }

    private function createAndPostTransaction(array $lineSpecs): GlTransactionHeader
    {
        $lines = [];
        foreach ($lineSpecs as $spec) {
            $lines[] = [
                'account_id' => $this->getTestAccountId($spec['account']),
                'line_description' => ucfirst($spec['account']->label()) . ' line',
                'debit_amount' => $spec['debit'],
                'credit_amount' => $spec['credit'],
            ];
        }

        $dto = new CreateGlTransactionDto([
            'fiscal_date' => now()->format('Y-m-d'),
            'transaction_description' => 'Test transaction',
            'gl_transaction_type' => GlTransactionTypeEnum::MANUAL_GL->value,
            'team_id' => currentTeamId(),
            'lines' => $lines,
        ]);

        $transaction = $this->glService->createTransaction($dto);
        $this->glService->postTransaction($transaction);
        
        return $transaction;
    }
}

<?php

namespace Condoedge\Finance\Tests\Unit;

use Condoedge\Finance\Kompo\GlTransactions\GlTransactionsTable;
use Condoedge\Finance\Kompo\GlTransactions\GlTransactionForm;
use Condoedge\Finance\Kompo\GlTransactions\GlTransactionLineForm;
use Condoedge\Finance\Models\GlTransactionHeader;
use Condoedge\Finance\Models\GlAccount;
use Condoedge\Finance\Tests\FinanceTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;

class GlTransactionUIComponentsTest extends FinanceTestCase
{
    use RefreshDatabase;

    #[Test]
    public function gl_transactions_table_renders_without_errors()
    {
        // Arrange
        $this->setupTestEnvironment();
        
        // Create some test transactions
        $this->createTestGlTransaction();
        
        // Act
        $table = new GlTransactionsTable();
        $query = $table->query();
        $headers = $table->headers();
        
        // Assert
        $this->assertNotNull($query);
        $this->assertIsArray($headers);
        $this->assertCount(8, $headers); // Should have 8 columns
        
        // Test that query returns data
        $results = $query->get();
        $this->assertGreaterThan(0, $results->count());
    }

    #[Test]
    public function gl_transaction_form_renders_for_new_transaction()
    {
        // Arrange
        $this->setupTestEnvironment();
        $this->createTestAccounts();
        
        // Act
        $form = new GlTransactionForm();
        $render = $form->render();
        
        // Assert
        $this->assertNotNull($render);
        // Should not be in view-only mode for new transactions
        $this->assertFalse($this->getPrivateProperty($form, 'isViewOnly'));
    }

    #[Test]
    public function gl_transaction_form_is_readonly_when_posted()
    {
        // Arrange
        $this->setupTestEnvironment();
        $transaction = $this->createTestGlTransaction(true); // Posted transaction
        
        // Act
        $form = new GlTransactionForm();
        $form->model = $transaction;
        $form->created(); // Re-run created to set isViewOnly
        
        // Assert
        $this->assertTrue($this->getPrivateProperty($form, 'isViewOnly'));
    }

    #[Test]
    public function gl_transaction_line_form_renders_with_account_options()
    {
        // Arrange
        $this->setupTestEnvironment();
        $this->createTestAccounts();
        
        // Act
        $lineForm = new GlTransactionLineForm();
        $lineForm->created();
        $render = $lineForm->render();
        
        // Assert
        $this->assertNotNull($render);
        
        // Test account select method
        $accountSelect = $this->invokePrivateMethod($lineForm, 'renderAccountSelect');
        $this->assertNotNull($accountSelect);
    }

    #[Test]
    public function gl_transaction_form_handles_submission_correctly()
    {
        // Arrange
        $this->setupTestEnvironment();
        $accounts = $this->createTestAccounts();
        
        $form = new GlTransactionForm();
        
        // Simulate request data
        request()->merge([
            'fiscal_date' => '2025-01-15',
            'gl_transaction_type' => 1,
            'transaction_description' => 'Test GL Entry',
            'glTransactionLines' => [
                [
                    'account_id' => $accounts[0]->account_id,
                    'line_description' => 'Debit line',
                    'debit_amount' => '100.00',
                    'credit_amount' => '0'
                ],
                [
                    'account_id' => $accounts[1]->account_id,
                    'line_description' => 'Credit line',
                    'debit_amount' => '0',
                    'credit_amount' => '100.00'
                ]
            ]
        ]);
        
        // Act
        $parsedData = $this->invokePrivateMethod($form, 'parseRequestData');
        
        // Assert
        $this->assertArrayHasKey('lines', $parsedData);
        $this->assertCount(2, $parsedData['lines']);
        $this->assertEquals('2025-01-15', $parsedData['fiscal_date']);
        $this->assertEquals($this->team->id, $parsedData['team_id']);
        
        // Verify line parsing
        $this->assertEquals($accounts[0]->account_id, $parsedData['lines'][0]['account_id']);
        $this->assertEquals(100.00, $parsedData['lines'][0]['debit_amount']);
        $this->assertEquals(0, $parsedData['lines'][0]['credit_amount']);
    }

    #[Test]
    public function gl_transactions_table_filters_work_correctly()
    {
        // Arrange
        $this->setupTestEnvironment();
        
        // Create transactions with different statuses
        $this->createTestGlTransaction(false); // Draft
        $this->createTestGlTransaction(true);  // Posted
        
        // Act
        $table = new GlTransactionsTable();
        $filters = $table->filters();
        
        // Assert
        $this->assertNotNull($filters);
        $this->assertNotEmpty($filters);
    }

    #[Test]
    public function gl_transaction_line_form_validates_account_selection()
    {
        // Arrange
        $this->setupTestEnvironment();
        
        // Act
        $lineForm = new GlTransactionLineForm();
        $rules = $lineForm->rules();
        
        // Assert
        $this->assertArrayHasKey('account_id', $rules);
        $this->assertStringContainsString('required', $rules['account_id']);
        $this->assertStringContainsString('exists:fin_gl_accounts,account_id', $rules['account_id']);
    }

    /**
     * Helper method to set up test environment
     */
    protected function setupTestEnvironment()
    {
        parent::setUp();
        
        // Set current team ID
        $this->actingAs($this->user);
        setCurrentTeamId($this->team->id);
    }

    /**
     * Helper method to create test accounts
     */
    protected function createTestAccounts()
    {
        $accounts = [];
        
        $accounts[] = GlAccount::create([
            'account_id' => '10-01-1000',
            'account_segments_descriptor' => 'Test - Department - Cash',
            'account_type' => 'asset',
            'is_active' => true,
            'allow_manual_entry' => true,
            'team_id' => $this->team->id
        ]);
        
        $accounts[] = GlAccount::create([
            'account_id' => '10-01-4000',
            'account_segments_descriptor' => 'Test - Department - Revenue',
            'account_type' => 'revenue',
            'is_active' => true,
            'allow_manual_entry' => true,
            'team_id' => $this->team->id
        ]);
        
        return $accounts;
    }

    /**
     * Helper method to create test GL transaction
     */
    protected function createTestGlTransaction($posted = false)
    {
        $accounts = $this->createTestAccounts();
        
        $transaction = GlTransactionHeader::create([
            'gl_transaction_id' => '2025-01-' . str_pad(rand(1, 999999), 6, '0', STR_PAD_LEFT),
            'gl_transaction_number' => rand(1, 999999),
            'fiscal_date' => '2025-01-15',
            'fiscal_year' => 2025,
            'gl_transaction_type' => 1,
            'transaction_description' => 'Test GL Entry',
            'is_balanced' => true,
            'is_posted' => $posted,
            'team_id' => $this->team->id
        ]);
        
        // Create lines
        $transaction->lines()->create([
            'account_id' => $accounts[0]->account_id,
            'line_description' => 'Debit line',
            'debit_amount' => 100.00,
            'credit_amount' => 0,
            'line_sequence' => 1
        ]);
        
        $transaction->lines()->create([
            'account_id' => $accounts[1]->account_id,
            'line_description' => 'Credit line',
            'debit_amount' => 0,
            'credit_amount' => 100.00,
            'line_sequence' => 2
        ]);
        
        return $transaction;
    }

    /**
     * Helper to access private properties
     */
    protected function getPrivateProperty($object, $property)
    {
        $reflection = new \ReflectionClass($object);
        $property = $reflection->getProperty($property);
        $property->setAccessible(true);
        return $property->getValue($object);
    }

    /**
     * Helper to invoke private methods
     */
    protected function invokePrivateMethod($object, $method, $args = [])
    {
        $reflection = new \ReflectionClass($object);
        $method = $reflection->getMethod($method);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $args);
    }
}

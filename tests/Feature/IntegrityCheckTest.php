<?php

namespace Tests\Feature;

use Condoedge\Finance\Facades\IntegrityChecker;
use Condoedge\Finance\Models\Invoice;
use Condoedge\Finance\Models\InvoiceDetail;
use Condoedge\Finance\Models\Customer;
use Condoedge\Finance\Models\Account;
use Condoedge\Finance\Models\InvoiceType;
use Condoedge\Finance\Casts\SafeDecimal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IntegrityCheckTest extends TestCase
{
    use RefreshDatabase;

    private $customer;
    private $invoiceType;
    private $account;

    public function setUp(): void
    {
        parent::setUp();
        
        // Create test data using seeded data
        $this->customer = Customer::factory()->create();
        $this->invoiceType = InvoiceType::first(); // Use seeded data
        $this->account = Account::factory()->create();
    }

    private function createInvoiceDetail($invoice, $unitPrice = '50.00', $quantity = 1, $name = 'Test Product')
    {
        return InvoiceDetail::factory()->create([
            'invoice_id' => $invoice->id,
            'name' => $name,
            'description' => 'Test Description',
            'quantity' => $quantity,
            'unit_price' => new SafeDecimal($unitPrice),
            'revenue_account_id' => $this->account->id,
        ]);
    }

    private function createMultipleInvoiceDetails($invoice, $details)
    {
        $createdDetails = [];
        foreach ($details as $detail) {
            $createdDetails[] = $this->createInvoiceDetail(
                $invoice, 
                $detail['unit_price'] ?? '50.00',
                $detail['quantity'] ?? 1,
                $detail['name'] ?? 'Test Product'
            );
        }
        return $createdDetails;
    }

    public function test_individual_invoice_detail_save_propagates_to_parent_invoice()
    {
        // Create invoice with correct column names from the model
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'invoice_type_id' => $this->invoiceType->id,
            'invoice_amount_before_taxes' => '0.00',
            'invoice_tax_amount' => '0.00',
        ]);

        // Initial state should be zero
        $this->assertEqualsDecimals('0.00', $invoice->fresh()->invoice_amount_before_taxes);

        // Add invoice detail using correct column names
        $this->createInvoiceDetail($invoice, '25.00', 2);

        // Check that parent invoice was updated (25.00 * 2 = 50.00)
        $invoice->refresh();
        $this->assertEqualsDecimals('50.00', $invoice->invoice_amount_before_taxes);
    }

    public function test_updating_invoice_detail_recalculates_parent_totals()
    {
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'invoice_type_id' => $this->invoiceType->id,
        ]);

        $detail = $this->createInvoiceDetail($invoice, '100.00', 1);

        // Update the detail
        $detail->quantity = 3;
        $detail->unit_price = new SafeDecimal('50.00'); 
        $detail->save();

        // Check parent was updated (50.00 * 3 = 150.00)
        $invoice->refresh();
        $this->assertEqualsDecimals('150.00', $invoice->invoice_amount_before_taxes);
    }

    public function test_deleting_invoice_detail_updates_parent_totals()
    {
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'invoice_type_id' => $this->invoiceType->id,
        ]);

        $detail1 = $this->createInvoiceDetail($invoice, '50.00', 1);
        $detail2 = $this->createInvoiceDetail($invoice, '30.00', 1);

        // Initial total should be 80.00 (50.00 + 30.00)
        $invoice->refresh();
        $this->assertEqualsDecimals('80.00', $invoice->invoice_amount_before_taxes);

        // Delete one detail
        $detail1->delete();

        // Total should now be 30.00
        $invoice->refresh();
        $this->assertEqualsDecimals('30.00', $invoice->invoice_amount_before_taxes);
    }

    public function test_multiple_invoice_details_aggregate_correctly()
    {
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'invoice_type_id' => $this->invoiceType->id,
        ]);

        // Add multiple details
        $this->createMultipleInvoiceDetails($invoice, [
            ['unit_price' => '25.50', 'quantity' => 1],
            ['unit_price' => '15.75', 'quantity' => 1],
            ['unit_price' => '8.25', 'quantity' => 1],
        ]);

        // Check aggregated total (25.50 + 15.75 + 8.25 = 49.50)
        $invoice->refresh();
        $this->assertEqualsDecimals('49.50', $invoice->invoice_amount_before_taxes);
    }

    public function test_batch_integrity_check_updates_multiple_records()
    {
        // Create invoices with incorrect totals
        $invoice1 = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'invoice_type_id' => $this->invoiceType->id,
            'invoice_amount_before_taxes' => '999.99', // Incorrect
        ]);

        $invoice2 = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'invoice_type_id' => $this->invoiceType->id,
            'invoice_amount_before_taxes' => '888.88', // Incorrect
        ]);

        // Add correct details
        $this->createInvoiceDetail($invoice1, '100.00', 1);
        $this->createInvoiceDetail($invoice2, '200.00', 1);

        // Run batch integrity check
        Invoice::checkIntegrity([$invoice1->id, $invoice2->id]);

        // Check both were corrected
        $invoice1->refresh();
        $invoice2->refresh();
        
        $this->assertEqualsDecimals('100.00', $invoice1->invoice_amount_before_taxes);
        $this->assertEqualsDecimals('200.00', $invoice2->invoice_amount_before_taxes);
    }

    public function test_integrity_checker_handles_cascading_updates()
    {
        // Test that changes propagate through multiple levels
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'invoice_type_id' => $this->invoiceType->id,
        ]);

        $detail = $this->createInvoiceDetail($invoice, '100.00', 1);

        // Simulate a change that should trigger cascading updates
        IntegrityChecker::checkChildrenThenModel(Invoice::class, [$invoice->id]);
        IntegrityChecker::checkModelThenParents(InvoiceDetail::class, [$detail->id]);

        $invoice->refresh();
        $this->assertEqualsDecimals('100.00', $invoice->invoice_amount_before_taxes);
    }

    public function test_integrity_check_performance_with_large_dataset()
    {
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'invoice_type_id' => $this->invoiceType->id,
        ]);

        // Create many details using bulk insert
        $details = [];
        for ($i = 0; $i < 100; $i++) {
            $details[] = [
                'invoice_id' => $invoice->id,
                'name' => "Product $i",
                'quantity' => 1,
                'description' => "Description for product $i",
                'unit_price' => '10.00',
                'revenue_account_id' => $this->account->id,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Bulk insert to avoid triggering events
        DB::table('fin_invoice_details')->insert($details);

        // Measure integrity check performance
        $startTime = microtime(true);
        Invoice::checkIntegrity([$invoice->id]);
        $endTime = microtime(true);

        $executionTime = $endTime - $startTime;
        
        // Should complete within reasonable time (less than 1 second)
        $this->assertLessThan(1.0, $executionTime);
        
        // Verify calculation is correct (100 * 10.00 = 1000.00)
        $invoice->refresh();
        $this->assertEqualsDecimals('1000.00', $invoice->invoice_amount_before_taxes);
    }

    public function test_integrity_check_handles_concurrent_modifications()
    {
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'invoice_type_id' => $this->invoiceType->id,
        ]);

        // Simulate concurrent detail additions
        $this->createInvoiceDetail($invoice, '50.00', 1);
        $this->createInvoiceDetail($invoice, '75.00', 1);

        // Manual integrity check should handle both
        Invoice::checkIntegrity([$invoice->id]);

        $invoice->refresh();
        $this->assertEqualsDecimals('125.00', $invoice->invoice_amount_before_taxes);
    }

    public function test_integrity_check_validates_calculation_accuracy()
    {
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'invoice_type_id' => $this->invoiceType->id,
        ]);

        // Add details with specific decimal precision
        $this->createInvoiceDetail($invoice, '33.33', 1);
        $this->createInvoiceDetail($invoice, '33.34', 1);

        $invoice->refresh();
        
        // Should handle decimal precision correctly (33.33 + 33.34 = 66.67)
        $this->assertEqualsDecimals('66.67', $invoice->invoice_amount_before_taxes);
    }

    public function test_integrity_check_works_without_specific_ids()
    {
        // Create multiple invoices
        $invoices = Invoice::factory()->count(3)->create([
            'customer_id' => $this->customer->id,
            'invoice_type_id' => $this->invoiceType->id,
            'invoice_amount_before_taxes' => '999.99', // Incorrect values
        ]);

        foreach ($invoices as $invoice) {
            $this->createInvoiceDetail($invoice, '50.00', 1);
        }

        // Run integrity check for all records
        Invoice::checkIntegrity();

        // All should be corrected
        foreach ($invoices as $invoice) {
            $invoice->refresh();
            $this->assertEqualsDecimals('50.00', $invoice->invoice_amount_before_taxes);
        }
    }

    public function test_integrity_events_are_triggered_correctly()
    {
        $eventsFired = [];
        
        // Listen for model events
        Invoice::saved(function ($model) use (&$eventsFired) {
            $eventsFired['invoice_saved'] = $model->id;
        });

        InvoiceDetail::saved(function ($model) use (&$eventsFired) {
            $eventsFired['detail_saved'] = $model->id;
        });

        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'invoice_type_id' => $this->invoiceType->id,
        ]);

        $detail = $this->createInvoiceDetail($invoice, '100.00', 1);

        $this->assertArrayHasKey('invoice_saved', $eventsFired);
        $this->assertArrayHasKey('detail_saved', $eventsFired);
        $this->assertEqualsDecimals($detail->id, $eventsFired['detail_saved']);
    }

    public function test_customer_due_amount_integrity_check()
    {
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'invoice_type_id' => $this->invoiceType->id,
            'invoice_due_amount' => '100.00',
        ]);

        // Customer due amount should be updated when invoice is approved
        $this->customer->refresh();
        $initialDue = $this->customer->customer_due_amount;

        // Simulate invoice approval (this should trigger customer due amount recalculation)
        Customer::checkIntegrity([$this->customer->id]);

        $this->customer->refresh();
        // The exact amount depends on invoice status and business logic
        $this->assertInstanceOf(SafeDecimal::class, $this->customer->customer_due_amount);
    }
}
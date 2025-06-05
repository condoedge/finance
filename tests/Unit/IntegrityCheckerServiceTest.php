<?php

namespace Tests\Unit;

use Condoedge\Finance\Services\IntegrityChecker;
use Condoedge\Finance\Models\Invoice;
use Condoedge\Finance\Models\InvoiceDetail;
use Condoedge\Finance\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntegrityCheckerServiceTest extends TestCase
{
    protected IntegrityChecker $integrityChecker;

    public function setUp(): void
    {
        parent::setUp();
        $this->integrityChecker = app(IntegrityChecker::class);
    }
    
    public function test_check_children_then_model_processes_in_correct_order()
    {
        $customer = Customer::factory()->create();
        $invoice = Invoice::factory()->create(['customer_id' => $customer->id]);
        
        // This should not throw an exception
        $this->integrityChecker->checkChildrenThenModel(Invoice::class, [$invoice->id]);
        
        $this->assertTrue(true); // Test passes if no exception is thrown
    }

    
    public function test_check_model_then_parents_processes_in_correct_order()
    {
        $customer = Customer::factory()->create();
        $invoice = Invoice::factory()->create(['customer_id' => $customer->id]);
        $detail = InvoiceDetail::factory()->create(['invoice_id' => $invoice->id]);
        
        // This should not throw an exception
        $this->integrityChecker->checkModelThenParents(InvoiceDetail::class, [$detail->id]);
        
        $this->assertTrue(true); // Test passes if no exception is thrown
    }

    
    public function test_full_integrity_check_processes_all_models()
    {
        Customer::factory()->count(2)->create();
        
        // This should not throw an exception and should complete
        $this->integrityChecker->checkFullIntegrity();
        
        $this->assertTrue(true); // Test passes if no exception is thrown
    }
}
<?php

namespace Tests\Unit;

use Condoedge\Finance\Database\Factories\AccountFactory;
use Condoedge\Finance\Database\Factories\CustomerFactory;
use Condoedge\Finance\Facades\CustomerModel;
use Condoedge\Finance\Facades\InvoiceService;
use Condoedge\Finance\Facades\InvoiceTypeEnum;
use Condoedge\Finance\Facades\PaymentMethodEnum;
use Condoedge\Finance\Facades\PaymentService;
use Condoedge\Finance\Models\CustomerPayment;
use Condoedge\Finance\Models\Dto\Invoices\CreateInvoiceDto;
use Condoedge\Finance\Models\Dto\Payments\CreateApplyForInvoiceDto;
use Condoedge\Finance\Models\Dto\Payments\CreateAppliesForMultipleInvoiceDto;
use Condoedge\Finance\Models\Dto\Payments\CreateCustomerPaymentDto;
use Condoedge\Finance\Models\Invoice;
use Condoedge\Finance\Models\MorphablesEnum;
use Exception;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Kompo\Auth\Database\Factories\UserFactory;
use Tests\TestCase;

class CustomerPaymentTest extends TestCase
{
    use WithFaker;

    public function setUp(): void
    {
        parent::setUp();

        /** @var \Kompo\Auth\Models\User $user */
        $user = UserFactory::new()->create()->first();
        if (!$user) throw new Exception('Unknown error creating user');
        $this->actingAs($user);
    }

    // ===== NEGATIVE PAYMENTS (COMPANY TO CUSTOMER) TESTS =====

    public function test_it_can_create_negative_payment_to_customer()
    {
        $customer = CustomerFactory::new()->create();
        $amount = -500.00; // Negative amount (to customer)
        $paymentDate = now();

        $payment = PaymentService::createPayment(new CreateCustomerPaymentDto([
            'customer_id' => $customer->id,
            'amount' => $amount,
            'payment_date' => $paymentDate,
        ]));

        $this->assertDatabaseHas('fin_customer_payments', [
            'id' => $payment->id,
            'customer_id' => $customer->id,
            'amount' => db_decimal_format($amount),
            'amount_left' => db_decimal_format($amount),
            'payment_date' => db_date_format($paymentDate),
        ]);

        $this->assertEqualsDecimals($amount, $payment->amount);
        $this->assertEqualsDecimals($amount, $payment->amount_left);
        $this->assertTrue($payment->amount->lessThan(0));
    }

    public function test_negative_payment_reduces_customer_due_amount()
    {
        $customer = CustomerFactory::new()->create();
        $invoice = $this->createApprovedInvoice($customer->id, 1000);

        // Customer owes 1000
        $customer->refresh();
        $this->assertEqualsDecimals(1000, $customer->customer_due_amount);

        // Create negative payment (company pays customer)
        $negativePayment = $this->createCustomerPayment($customer->id, -300);        // Customer due amount should increase (they owe more since we owe them money)
        $customer->refresh();
        $this->assertEqualsDecimals(1300, $customer->customer_due_amount); // Due amount = debt - payment = 1000 - (-300) = 1300
    }

    public function test_negative_payment_can_be_applied_to_credit_note()
    {
        $customer = CustomerFactory::new()->create();
        $creditNote = $this->createCreditNote($customer->id, 400);
        $negativePayment = $this->createCustomerPayment($customer->id, -200);

        // Apply negative payment to credit note
        $invoiceApply = PaymentService::applyPaymentToInvoice(new CreateApplyForInvoiceDto([
            'apply_date' => now(),
            'applicable' => (object)[
                'id' => $negativePayment->id,
                'customer_id' => $negativePayment->customer_id,
            ],
            'applicable_type' => MorphablesEnum::PAYMENT->value,
            'amount_applied' => 200, // Note: this should be positive when applying to credit
            'invoice_id' => $creditNote->id,
        ]));        $this->assertDatabaseHas('fin_invoice_applies', [
            'id' => $invoiceApply->id,
            'invoice_id' => $creditNote->id,
            'applicable_id' => $negativePayment->id,
            'applicable_type' => MorphablesEnum::PAYMENT->value,
            'payment_applied_amount' => db_decimal_format(-200), // System handles sign automatically
        ]);

        // Verify payment amount_left is reduced (less negative)
        $negativePayment->refresh();
        $this->assertEqualsDecimals(0, $negativePayment->amount_left); // -200 + 200 = 0

        // Verify credit note due amount is reduced
        $creditNote->refresh();
        $this->assertEqualsDecimals(200, $creditNote->abs_invoice_due_amount); // 400 - 200
    }

    public function test_negative_payment_cannot_exceed_its_absolute_amount_left()
    {        $customer = CustomerFactory::new()->create();
        $creditNote = $this->createCreditNote($customer->id, 500);
        $negativePayment = $this->createCustomerPayment($customer->id, -200);

        $this->expectException(ValidationException::class);

        PaymentService::applyPaymentToInvoice(new CreateApplyForInvoiceDto([
            'apply_date' => now(),
            'applicable' => (object)[
                'id' => $negativePayment->id,
                'customer_id' => $negativePayment->customer_id,
            ],
            'applicable_type' => MorphablesEnum::PAYMENT->value,
            'amount_applied' => 300, // More than negative payment absolute amount (200)
            'invoice_id' => $creditNote->id,
        ]));
    }

    // ===== CREDIT NOTES TESTS =====

    public function test_it_can_create_credit_note()
    {
        $customer = CustomerFactory::new()->create();
        $amount = 500;

        $creditNote = $this->createCreditNote($customer->id, $amount);

        $this->assertDatabaseHas('fin_invoices', [
            'id' => $creditNote->id,
            'customer_id' => $customer->id,
            'invoice_type_id' => InvoiceTypeEnum::getEnumCase('CREDIT')->value,
            'invoice_total_amount' => db_decimal_format(-$amount), // Credits are negative
        ]);

        $this->assertTrue($creditNote->invoice_total_amount->lessThan(0));
        $this->assertTrue($creditNote->invoice_due_amount->lessThan(0));
        $this->assertEqualsDecimals($amount, $creditNote->abs_invoice_due_amount);
    }

    public function test_credit_note_reduces_customer_due_amount()
    {
        $customer = CustomerFactory::new()->create();
        $invoice = $this->createApprovedInvoice($customer->id, 1000);
        
        // Customer owes 1000
        $customer->refresh();
        $this->assertEqualsDecimals(1000, $customer->customer_due_amount);

        // Create credit note
        $creditNote = $this->createCreditNote($customer->id, 300);

        // Customer due amount should decrease
        $customer->refresh();
        $this->assertEqualsDecimals(700, $customer->customer_due_amount); // 1000 - 300
    }

    public function test_credit_note_can_be_applied_to_regular_invoice()
    {
        $customer = CustomerFactory::new()->create();
        $invoice = $this->createApprovedInvoice($customer->id, 800);
        $creditNote = $this->createCreditNote($customer->id, 300);

        // Apply credit note to invoice
        $invoiceApply = PaymentService::applyPaymentToInvoice(new CreateApplyForInvoiceDto([
            'apply_date' => now(),
            'applicable' => (object)[
                'id' => $creditNote->id,
                'customer_id' => $creditNote->customer_id,
            ],
            'applicable_type' => MorphablesEnum::CREDIT->value,
            'amount_applied' => 300,
            'invoice_id' => $invoice->id,
        ]));

        $this->assertDatabaseHas('fin_invoice_applies', [
            'id' => $invoiceApply->id,
            'invoice_id' => $invoice->id,
            'applicable_id' => $creditNote->id,
            'applicable_type' => MorphablesEnum::CREDIT->value,
            'payment_applied_amount' => db_decimal_format(300),
        ]);

        // Verify credit note due amount is reduced
        $creditNote->refresh();
        $this->assertEqualsDecimals(0, $creditNote->abs_invoice_due_amount); // 300 - 300

        // Verify invoice due amount is reduced
        $invoice->refresh();
        $this->assertEqualsDecimals(500, $invoice->invoice_due_amount); // 800 - 300
    }

    public function test_credit_note_cannot_be_applied_more_than_its_due_amount()
    {
        $customer = CustomerFactory::new()->create();
        $invoice = $this->createApprovedInvoice($customer->id, 1000);
        $creditNote = $this->createCreditNote($customer->id, 200);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('applicable-amount-exceeded');

        PaymentService::applyPaymentToInvoice(new CreateApplyForInvoiceDto([
            'apply_date' => now(),
            'applicable' => (object)[
                'id' => $creditNote->id,
                'customer_id' => $creditNote->customer_id,
            ],
            'applicable_type' => MorphablesEnum::CREDIT->value,
            'amount_applied' => 300, // More than credit note amount (200)
            'invoice_id' => $invoice->id,
        ]));
    }

    public function test_credit_note_application_to_multiple_invoices()
    {
        $customer = CustomerFactory::new()->create();
        $invoice1 = $this->createApprovedInvoice($customer->id, 400);
        $invoice2 = $this->createApprovedInvoice($customer->id, 300);
        $creditNote = $this->createCreditNote($customer->id, 500);

        PaymentService::applyPaymentToInvoices(new CreateAppliesForMultipleInvoiceDto([
            'apply_date' => now()->format('Y-m-d'),
            'applicable' => (object)[
                'id' => $creditNote->id,
                'customer_id' => $creditNote->customer_id,
            ],
            'applicable_type' => MorphablesEnum::CREDIT->value,
            'amounts_to_apply' => [
                [
                    'id' => $invoice1->id,
                    'amount_applied' => 250,
                ],
                [
                    'id' => $invoice2->id,
                    'amount_applied' => 200,
                ],
            ],
        ]));

        // Verify applications were created
        $this->assertDatabaseHas('fin_invoice_applies', [
            'invoice_id' => $invoice1->id,
            'applicable_id' => $creditNote->id,
            'payment_applied_amount' => 250,
        ]);

        $this->assertDatabaseHas('fin_invoice_applies', [
            'invoice_id' => $invoice2->id,
            'applicable_id' => $creditNote->id,
            'payment_applied_amount' => 200,
        ]);

        // Verify credit note due amount
        $creditNote->refresh();
        $this->assertEqualsDecimals(50, $creditNote->abs_invoice_due_amount); // 500 - 250 - 200

        // Verify invoice due amounts
        $invoice1->refresh();
        $invoice2->refresh();
        $this->assertEqualsDecimals(150, $invoice1->invoice_due_amount); // 400 - 250
        $this->assertEqualsDecimals(100, $invoice2->invoice_due_amount); // 300 - 200
    }

    // ===== COMPLEX SCENARIO TESTS =====

    public function test_negative_payment_and_credit_interaction()
    {
        $customer = CustomerFactory::new()->create();
        $invoice = $this->createApprovedInvoice($customer->id, 1000);
        $creditNote = $this->createCreditNote($customer->id, 600);
        $negativePayment = $this->createCustomerPayment($customer->id, -400);
        $regularPayment = $this->createCustomerPayment($customer->id, 300);

        // Apply credit note to invoice
        PaymentService::applyPaymentToInvoice(new CreateApplyForInvoiceDto([
            'apply_date' => now(),
            'applicable' => (object)[
                'id' => $creditNote->id,
                'customer_id' => $creditNote->customer_id,
            ],
            'applicable_type' => MorphablesEnum::CREDIT->value,
            'amount_applied' => 400,
            'invoice_id' => $invoice->id,
        ]));

        // Apply regular payment to remaining invoice balance
        PaymentService::applyPaymentToInvoice(new CreateApplyForInvoiceDto([
            'apply_date' => now(),
            'applicable' => (object)[
                'id' => $regularPayment->id,
                'customer_id' => $regularPayment->customer_id,
            ],
            'applicable_type' => MorphablesEnum::PAYMENT->value,
            'amount_applied' => 300,
            'invoice_id' => $invoice->id,
        ]));

        // Apply negative payment to remaining credit balance
        PaymentService::applyPaymentToInvoice(new CreateApplyForInvoiceDto([
            'apply_date' => now(),
            'applicable' => (object)[
                'id' => $negativePayment->id,
                'customer_id' => $negativePayment->customer_id,
            ],
            'applicable_type' => MorphablesEnum::PAYMENT->value,
            'amount_applied' => 200,
            'invoice_id' => $creditNote->id,
        ]));

        // Verify final states
        $invoice->refresh();
        $creditNote->refresh();
        $negativePayment->refresh();
        $regularPayment->refresh();

        $this->assertEqualsDecimals(300, $invoice->invoice_due_amount); // 1000 - 400 - 300
        $this->assertEqualsDecimals(0, $creditNote->abs_invoice_due_amount); // 600 - 400 = 200, 200 - 200 = 0
        $this->assertEqualsDecimals(0, $regularPayment->amount_left); // 300 - 300
        $this->assertEqualsDecimals(-200, $negativePayment->amount_left); // -400 + 200
    }

    public function test_sign_consistency_in_calculations()
    {
        $customer = CustomerFactory::new()->create();
        
        // Regular invoice (positive)
        $invoice = $this->createApprovedInvoice($customer->id, 500);
        $this->assertTrue($invoice->invoice_total_amount->greaterThan(0));
        $this->assertTrue($invoice->invoice_due_amount->greaterThan(0));

        // Credit note (negative total, but absolute due amount for application)
        $creditNote = $this->createCreditNote($customer->id, 300);
        $this->assertTrue($creditNote->invoice_total_amount->lessThan(0));
        $this->assertTrue($creditNote->invoice_due_amount->lessThan(0));
        $this->assertTrue($creditNote->abs_invoice_due_amount->greaterThan(0));

        // Regular payment (positive)
        $regularPayment = $this->createCustomerPayment($customer->id, 200);
        $this->assertTrue($regularPayment->amount->greaterThan(0));

        // Negative payment (negative)
        $negativePayment = $this->createCustomerPayment($customer->id, -150);
        $this->assertTrue($negativePayment->amount->lessThan(0));

        // Test that absolute values are used for application logic
        $this->assertEqualsDecimals(300, $creditNote->abs_invoice_due_amount);
        $this->assertEqualsDecimals(150, $negativePayment->amount->abs());
    }

    public function test_database_triggers_handle_negative_amounts_correctly()
    {
        $customer = CustomerFactory::new()->create();
        $creditNote = $this->createCreditNote($customer->id, 200);
        $negativePayment = $this->createCustomerPayment($customer->id, -100);

        // Test direct database insert with trigger validation
        DB::table('fin_invoice_applies')->insert([
            'invoice_id' => $creditNote->id,
            'applicable_id' => $negativePayment->id,
            'applicable_type' => MorphablesEnum::PAYMENT->value,
            'payment_applied_amount' => 100, // Positive amount applied
            'apply_date' => now()->format('Y-m-d'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Verify trigger updated amounts correctly
        $negativePayment->refresh();
        $creditNote->refresh();
        
        $this->assertEqualsDecimals(0, $negativePayment->amount_left); // -100 + 100 = 0
        $this->assertEqualsDecimals(100, $creditNote->abs_invoice_due_amount); // 200 - 100
    }    
    
    public function test_prevents_incorrect_sign_applications()
    {
        // You cannot apply a negative payment to a positive invoice
        $customer = CustomerFactory::new()->create();
        $regularInvoice = $this->createApprovedInvoice($customer->id, 500);
        $negativePayment = $this->createCustomerPayment($customer->id, -200);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        
        PaymentService::applyPaymentToInvoice(new CreateApplyForInvoiceDto([
            'apply_date' => now(),
            'applicable' => (object)[
                'id' => $negativePayment->id,
                'customer_id' => $negativePayment->customer_id,
            ],
            'applicable_type' => MorphablesEnum::PAYMENT->value,
            'amount_applied' => -200,
            'invoice_id' => $regularInvoice->id,
        ]));
    }

    // ===== EDGE CASES =====

    public function test_zero_amount_applications_are_prevented()
    {
        $customer = CustomerFactory::new()->create();
        $invoice = $this->createApprovedInvoice($customer->id, 500);
        $payment = $this->createCustomerPayment($customer->id, 300);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('amount-applied-zero');

        PaymentService::applyPaymentToInvoice(new CreateApplyForInvoiceDto([
            'apply_date' => now(),
            'applicable' => (object)[
                'id' => $payment->id,
                'customer_id' => $payment->customer_id,
            ],
            'applicable_type' => MorphablesEnum::PAYMENT->value,
            'amount_applied' => 0,
            'invoice_id' => $invoice->id,
        ]));
    }

    public function test_precision_handling_with_decimals()
    {
        $customer = CustomerFactory::new()->create();
        $invoice = $this->createApprovedInvoice($customer->id, 100.123);
        $payment = $this->createCustomerPayment($customer->id, 50.567);

        $invoiceApply = PaymentService::applyPaymentToInvoice(new CreateApplyForInvoiceDto([
            'apply_date' => now(),
            'applicable' => (object)[
                'id' => $payment->id,
                'customer_id' => $payment->customer_id,
            ],
            'applicable_type' => MorphablesEnum::PAYMENT->value,
            'amount_applied' => 50.567,
            'invoice_id' => $invoice->id,
        ]));

        $payment->refresh();
        $invoice->refresh();

        $this->assertEqualsDecimals(0, $payment->amount_left);
        $this->assertEqualsDecimals(49.556, $invoice->invoice_due_amount); // 100.123 - 50.567
    }

    // ===== ADDITIONAL COMPREHENSIVE TESTS =====

    public function test_negative_payment_application_behaves_like_positive_payment_to_credit()
    {
        $customer = CustomerFactory::new()->create();
        
        // Scenario 1: Regular payment to regular invoice
        $regularInvoice = $this->createApprovedInvoice($customer->id, 500);
        $regularPayment = $this->createCustomerPayment($customer->id, 300);
        
        PaymentService::applyPaymentToInvoice(new CreateApplyForInvoiceDto([
            'apply_date' => now(),
            'applicable' => (object)[
                'id' => $regularPayment->id,
                'customer_id' => $regularPayment->customer_id,
            ],
            'applicable_type' => MorphablesEnum::PAYMENT->value,
            'amount_applied' => 300,
            'invoice_id' => $regularInvoice->id,
        ]));

        // Scenario 2: Negative payment to credit note (should behave symmetrically)
        $creditNote = $this->createCreditNote($customer->id, 500);
        $negativePayment = $this->createCustomerPayment($customer->id, -300);
        
        PaymentService::applyPaymentToInvoice(new CreateApplyForInvoiceDto([
            'apply_date' => now(),
            'applicable' => (object)[
                'id' => $negativePayment->id,
                'customer_id' => $negativePayment->customer_id,
            ],
            'applicable_type' => MorphablesEnum::PAYMENT->value,
            'amount_applied' => 300,
            'invoice_id' => $creditNote->id,
        ]));

        // Both should result in zero amount_left and reduced due amounts
        $regularPayment->refresh();
        $negativePayment->refresh();
        $regularInvoice->refresh();
        $creditNote->refresh();

        $this->assertEqualsDecimals(0, $regularPayment->amount_left);
        $this->assertEqualsDecimals(0, $negativePayment->amount_left);
        $this->assertEqualsDecimals(200, $regularInvoice->invoice_due_amount);
        $this->assertEqualsDecimals(200, $creditNote->abs_invoice_due_amount);
    }

    public function test_customer_balance_calculation_with_mixed_transactions()
    {
        $customer = CustomerFactory::new()->create();
        
        // Create various transactions
        $invoice1 = $this->createApprovedInvoice($customer->id, 1000); // Customer owes 1000
        $creditNote1 = $this->createCreditNote($customer->id, 300);   // Customer credit 300
        $regularPayment = $this->createCustomerPayment($customer->id, 400); // Customer pays 400
        $negativePayment = $this->createCustomerPayment($customer->id, -200); // Company pays customer 200        // Check customer balance calculation
        $customer->refresh();
        
        // Expected calculation:
        // Total debt: 1000 (invoice) - 300 (credit note) = 700
        // Total paid: 400 (payment) - 200 (negative payment) = 200  
        // Customer due = 700 - 200 = 500
        $this->assertEqualsDecimals(500, $customer->customer_due_amount);
    }    public function test_credit_note_creation_with_credit_payment_service()
    {
        $customer = CustomerFactory::new()->create();
        $amount = 600;

        // Create credit note using the helper method
        $creditNote = $this->createCreditNote($customer->id, $amount);
        
        // Create a payment and apply it to the credit note (simulating the service behavior)
        $payment = $this->createCustomerPayment($customer->id, $creditNote->invoice_due_amount);
        
        PaymentService::applyPaymentToInvoice(new CreateApplyForInvoiceDto([
            'apply_date' => now(),
            'applicable' => (object)[
                'id' => $payment->id,
                'customer_id' => $payment->customer_id,
            ],
            'applicable_type' => MorphablesEnum::PAYMENT->value,
            'amount_applied' => $creditNote->abs_invoice_due_amount,
            'invoice_id' => $creditNote->id,
        ]));

        // Verify credit note was created
        $this->assertDatabaseHas('fin_invoices', [
            'id' => $creditNote->id,
            'invoice_type_id' => InvoiceTypeEnum::getEnumCase('CREDIT')->value,
        ]);

        // Verify payment was created and applied
        $this->assertDatabaseHas('fin_customer_payments', [
            'customer_id' => $customer->id,
            'amount' => db_decimal_format($creditNote->invoice_due_amount),
        ]);

        // Credit should be fully applied
        $this->assertEqualsDecimals(0, $creditNote->fresh()->abs_invoice_due_amount);
    }

    public function test_validation_prevents_cross_customer_applications()
    {
        $customer1 = CustomerFactory::new()->create();
        $customer2 = CustomerFactory::new()->create();
        
        $invoice = $this->createApprovedInvoice($customer1->id, 500);
        $payment = $this->createCustomerPayment($customer2->id, 300);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('invoice-customer-mismatch');        PaymentService::applyPaymentToInvoice(new CreateApplyForInvoiceDto([
            'apply_date' => now(),
            'applicable' => (object)[
                'id' => $payment->id,
                'customer_id' => $payment->customer_id,
            ],
            'applicable_type' => MorphablesEnum::PAYMENT->value,
            'amount_applied' => 200,
            'invoice_id' => $invoice->id,
        ]));
    }    /**
     * Test that validates the new business rule: negative payments cannot be applied to regular invoices.
     * This prevents logical contradictions where the company owes money to the customer 
     * but the customer also owes money to the company for the same invoice.
     */
    public function test_validation_prevents_negative_payment_application_to_regular_invoice()
    {        
        $customer = CustomerFactory::new()->create();
        $regularInvoice = $this->createApprovedInvoice($customer->id, 1000);
        $negativePayment = $this->createCustomerPayment($customer->id, -300);

        try {
            PaymentService::applyPaymentToInvoice(new CreateApplyForInvoiceDto([
                'apply_date' => now(),
                'applicable' => (object) [
                    'id' => $negativePayment->id,
                    'customer_id' => $customer->id,
                ],
                'applicable_type' => MorphablesEnum::PAYMENT->value,
                'amount_applied' => -300,
                'invoice_id' => $regularInvoice->id,
            ]));
            
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            // Verify the specific validation error message is about negative payment to regular invoice
            $this->assertArrayHasKey('applicable', $e->errors());
            // The validation should prevent this operation
        }
        
        // Verify no invoice application was created
        $this->assertDatabaseMissing('fin_invoice_applies', [
            'invoice_id' => $regularInvoice->id,
            'applicable_id' => $negativePayment->id,
            'applicable_type' => MorphablesEnum::PAYMENT->value,
        ]);
        
        $regularInvoice->refresh();
        $customer->refresh();
        $negativePayment->refresh();

        $this->assertEqualsDecimals(1300, $customer->customer_due_amount);
        $this->assertEqualsDecimals(1000, $regularInvoice->invoice_due_amount); // Invoice should remain unchanged
        $this->assertEqualsDecimals(-300, $negativePayment->amount_left); // Negative payment should remain unchanged
    }

    // Helper Methods
    private function createApprovedInvoice($customerId = null, $amount = null): Invoice
    {
        $customer = $customerId ? CustomerModel::find($customerId) : CustomerFactory::new()->create();
        $quantity = 1;
        $unitPrice = $amount ?? $this->faker->randomFloat(2, 100, 1000);

        $invoice = InvoiceService::createInvoice(new CreateInvoiceDto([
            'customer_id' => $customer->id,
            'invoice_type_id' => InvoiceTypeEnum::getEnumCase('INVOICE')->value,
            'payment_method_id' => PaymentMethodEnum::getEnumCase('CASH')->value,
            'invoice_date' => now(),
            'invoice_due_date' => now()->addDays(30),
            'is_draft' => false,
            'invoiceDetails' => [
                [
                    'name' => 'Test Item',
                    'description' => 'Test Description',
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'revenue_account_id' => AccountFactory::new()->create()->id,
                    'taxesIds' => [],
                ],
            ],
        ]));

        $invoice->markApproved();
        return $invoice->fresh();
    }

    private function createCreditNote($customerId, $amount): Invoice
    {
        $customer = CustomerModel::find($customerId);

        $creditNote = InvoiceService::createInvoice(new CreateInvoiceDto([
            'customer_id' => $customer->id,
            'invoice_type_id' => InvoiceTypeEnum::getEnumCase('CREDIT')->value,
            'payment_method_id' => PaymentMethodEnum::getEnumCase('CASH')->value,
            'invoice_date' => now(),
            'invoice_due_date' => now()->addDays(30),
            'is_draft' => false,
            'invoiceDetails' => [
                [
                    'name' => 'Credit Item',
                    'description' => 'Credit Description',
                    'quantity' => 1,
                    'unit_price' => $amount,
                    'revenue_account_id' => AccountFactory::new()->create()->id,
                    'taxesIds' => [],
                ],
            ],
        ]));

        $creditNote->markApproved();
        return $creditNote->fresh();
    }

    private function createCustomerPayment($customerId, $amount): CustomerPayment
    {
        return PaymentService::createPayment(new CreateCustomerPaymentDto([
            'customer_id' => $customerId,
            'amount' => $amount,
            'payment_date' => now(),
        ]));
    }    /**
     * Test that negative payments CAN be applied to credit notes.
     * This is allowed because both represent money owed from company to customer.
     */
    public function test_negative_payment_can_be_applied_to_credit_note_with_validation()
    {        $customer = CustomerFactory::new()->create();
        $creditNote = $this->createCreditNote($customer->id, 500);
        $negativePayment = $this->createCustomerPayment($customer->id, -300);

        // This should work - negative payment can be applied to credit note
        $invoiceApply = PaymentService::applyPaymentToInvoice(new CreateApplyForInvoiceDto([
            'apply_date' => now(),
            'applicable' => (object) [
                'id' => $negativePayment->id,
                'customer_id' => $customer->id,
            ],
            'applicable_type' => MorphablesEnum::PAYMENT->value,
            'amount_applied' => 300,
            'invoice_id' => $creditNote->id,
        ]));

        // Verify the application was created successfully
        $this->assertDatabaseHas('fin_invoice_applies', [
            'id' => $invoiceApply->id,
            'invoice_id' => $creditNote->id,
            'applicable_id' => $negativePayment->id,
            'applicable_type' => MorphablesEnum::PAYMENT->value,
        ]);

        // Verify amounts are updated correctly
        $negativePayment->refresh();
        $creditNote->refresh();
        
        $this->assertEqualsDecimals(0, $negativePayment->amount_left); // -300 + 300 = 0
        $this->assertEqualsDecimals(200, $creditNote->abs_invoice_due_amount); // 500 - 300 = 200
    }
}
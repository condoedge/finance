<?php

namespace Tests\Unit;

use Condoedge\Finance\Database\Factories\GlAccountFactory;
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
use Illuminate\Validation\ValidationException;
use Kompo\Auth\Database\Factories\UserFactory;
use Tests\TestCase;

class InvoicePaymentValidationsTest extends TestCase
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

    public function test_it_validates_payment_amount_left_calculation()
    {
        $customer = CustomerFactory::new()->create();
        $payment = PaymentService::createPayment(new CreateCustomerPaymentDto([
            'customer_id' => $customer->id,
            'amount' => 1000,
            'payment_date' => now()->format('Y-m-d'),
        ]));

        // Verify initial state
        $this->assertEqualsDecimals(1000, $payment->amount_left);
        $this->assertEqualsDecimals(1000, $payment->amount);

        $invoice1 = $this->createApprovedInvoice($customer->id, 300);
        $invoice2 = $this->createApprovedInvoice($customer->id, 400);

        // Apply to first invoice
        PaymentService::applyPaymentToInvoice(new CreateApplyForInvoiceDto([
            'apply_date' => now()->format('Y-m-d'),
            'applicable' => (object)[
                'id' => $payment->id,
                'customer_id' => $payment->customer_id,
            ],
            'applicable_type' => MorphablesEnum::PAYMENT->value,
            'amount_applied' => 200,
            'invoice_id' => $invoice1->id,
        ]));

        $payment->refresh();
        $this->assertEqualsDecimals(800, $payment->amount_left);
        $this->assertEqualsDecimals(1000, $payment->amount);

        // Apply to second invoice
        PaymentService::applyPaymentToInvoice(new CreateApplyForInvoiceDto([
            'apply_date' => now()->format('Y-m-d'),
            'applicable' => (object)[
                'id' => $payment->id,
                'customer_id' => $payment->customer_id,
            ],
            'applicable_type' => MorphablesEnum::PAYMENT->value,
            'amount_applied' => 350,
            'invoice_id' => $invoice2->id,
        ]));

        $payment->refresh();
        $this->assertEqualsDecimals(450, $payment->amount_left);
        $this->assertEqualsDecimals(1000, $payment->amount);
    }

    public function test_it_validates_invoice_due_amount_calculation()
    {
        $customer = CustomerFactory::new()->create();
        $invoice = $this->createApprovedInvoice($customer->id, 500);
        
        // Verify initial state
        $this->assertEqualsDecimals(500, $invoice->invoice_total_amount);
        $this->assertEqualsDecimals(500, $invoice->invoice_due_amount);
        $this->assertEqualsDecimals(500, $invoice->abs_invoice_due_amount);

        $payment1 = $this->createCustomerPayment($customer->id, 200);
        $payment2 = $this->createCustomerPayment($customer->id, 150);

        // First payment application
        PaymentService::applyPaymentToInvoice(new CreateApplyForInvoiceDto([
            'apply_date' => now()->format('Y-m-d'),
            'applicable' => (object)[
                'id' => $payment1->id,
                'customer_id' => $payment1->customer_id,
            ],
            'applicable_type' => MorphablesEnum::PAYMENT->value,
            'amount_applied' => 200,
            'invoice_id' => $invoice->id,
        ]));

        $invoice->refresh();
        $this->assertEqualsDecimals(300, $invoice->invoice_due_amount);
        $this->assertEqualsDecimals(300, $invoice->abs_invoice_due_amount);

        // Second payment application
        PaymentService::applyPaymentToInvoice(new CreateApplyForInvoiceDto([
            'apply_date' => now()->format('Y-m-d'),
            'applicable' => (object)[
                'id' => $payment2->id,
                'customer_id' => $payment2->customer_id,
            ],
            'applicable_type' => MorphablesEnum::PAYMENT->value,
            'amount_applied' => 150,
            'invoice_id' => $invoice->id,
        ]));

        $invoice->refresh();
        $this->assertEqualsDecimals(150, $invoice->invoice_due_amount);
        $this->assertEqualsDecimals(150, $invoice->abs_invoice_due_amount);
    }

    public function test_it_prevents_negative_payment_amount_left()
    {
        $customer = CustomerFactory::new()->create();
        $payment = $this->createCustomerPayment($customer->id, 100);
        $invoice = $this->createApprovedInvoice($customer->id, 200);

        // First application - valid
        PaymentService::applyPaymentToInvoice(new CreateApplyForInvoiceDto([
            'apply_date' => now()->format('Y-m-d'),
            'applicable' => (object)[
                'id' => $payment->id,
                'customer_id' => $payment->customer_id,
            ],
            'applicable_type' => MorphablesEnum::PAYMENT->value,
            'amount_applied' => 80,
            'invoice_id' => $invoice->id,
        ]));

        // Second application - should fail
        $this->expectException(ValidationException::class);

        PaymentService::applyPaymentToInvoice(new CreateApplyForInvoiceDto([
            'apply_date' => now()->format('Y-m-d'),
            'applicable' => (object)[
                'id' => $payment->id,
                'customer_id' => $payment->customer_id,
            ],
            'applicable_type' => MorphablesEnum::PAYMENT->value,
            'amount_applied' => 30, // 80 + 30 = 110 > 100
            'invoice_id' => $invoice->id,
        ]));
    }

    public function test_it_validates_zero_amount_applications()
    {
        $customer = CustomerFactory::new()->create();
        $payment = $this->createCustomerPayment($customer->id, 100);
        $invoice = $this->createApprovedInvoice($customer->id, 200);

        $this->expectException(ValidationException::class);

        PaymentService::applyPaymentToInvoice(new CreateApplyForInvoiceDto([
            'apply_date' => now()->format('Y-m-d'),
            'applicable' => (object)[
                'id' => $payment->id,
                'customer_id' => $payment->customer_id,
            ],
            'applicable_type' => MorphablesEnum::PAYMENT->value,
            'amount_applied' => 0,
            'invoice_id' => $invoice->id,
        ]));
    }

    public function test_it_converts_negative_amount_applications_into_positives_if_required()
    {
        $customer = CustomerFactory::new()->create();
        $payment = $this->createCustomerPayment($customer->id, 100);
        $invoice = $this->createApprovedInvoice($customer->id, 200);

        $apply = PaymentService::applyPaymentToInvoice(new CreateApplyForInvoiceDto([
            'apply_date' => now()->format('Y-m-d'),
            'applicable' => (object)[
                'id' => $payment->id,
                'customer_id' => $payment->customer_id,
            ],
            'applicable_type' => MorphablesEnum::PAYMENT->value,
            'amount_applied' => -50,
            'invoice_id' => $invoice->id,
        ]));

        $apply->refresh();
        $payment->refresh();
        $invoice->refresh();

        $this->assertEqualsDecimals(50, $payment->amount_left);
        $this->assertEqualsDecimals(150, $invoice->invoice_due_amount); // 200 - 50 = 150
        $this->assertEqualsDecimals(50, $apply->payment_applied_amount); // Should be stored as positive
    }

    public function test_it_validates_multiple_invoice_applications_with_amount_constraints()
    {
        $customer = CustomerFactory::new()->create();
        $payment = $this->createCustomerPayment($customer->id, 500);
        $invoice1 = $this->createApprovedInvoice($customer->id, 300);
        $invoice2 = $this->createApprovedInvoice($customer->id, 400);
        $invoice3 = $this->createApprovedInvoice($customer->id, 200);

        // Valid multiple applications
        PaymentService::applyPaymentToInvoices(new CreateAppliesForMultipleInvoiceDto([
            'apply_date' => now()->format('Y-m-d'),
            'applicable' => (object)[
                'id' => $payment->id,
                'customer_id' => $payment->customer_id,
            ],
            'applicable_type' => MorphablesEnum::PAYMENT->value,
            'amounts_to_apply' => [
                [
                    'id' => $invoice1->id,
                    'amount_applied' => 200,
                ],
                [
                    'id' => $invoice2->id,
                    'amount_applied' => 250,
                ],
            ],
        ]));

        // Should fail - total exceeds payment amount
        $this->expectException(ValidationException::class);

        PaymentService::applyPaymentToInvoices(new CreateAppliesForMultipleInvoiceDto([
            'apply_date' => now()->format('Y-m-d'),
            'applicable' => (object)[
                'id' => $payment->id,
                'customer_id' => $payment->customer_id,
            ],
            'applicable_type' => MorphablesEnum::PAYMENT->value,
            'applies' => [
                [
                    'invoice_id' => $invoice3->id,
                    'amount_applied' => 100, // 450 + 100 = 550 > 500 remaining
                ],
            ],
        ]));
    }

    public function test_it_validates_draft_invoice_application_prevention()
    {
        $customer = CustomerFactory::new()->create();
        $payment = $this->createCustomerPayment($customer->id, 500);
        
        // Create draft invoice (not approved)
        $draftInvoice = InvoiceService::createInvoice(new CreateInvoiceDto([
            'customer_id' => $customer->id,
            'invoice_type_id' => InvoiceTypeEnum::getEnumCase('INVOICE')->value,
            'payment_method_id' => PaymentMethodEnum::getEnumCase('CASH')->value,
            'invoice_date' => now()->format('Y-m-d'),
            'invoice_due_date' => now()->addDays(30),
            'is_draft' => true,
            'invoiceDetails' => [
                [
                    'name' => 'Test Item',
                    'description' => 'Test Description',
                    'quantity' => 1,
                    'unit_price' => 300,
                    'revenue_account_id' => GlAccountFactory::new()->create()->id,
                    'taxesIds' => [],
                ],
            ],
        ]));

        // Should prevent application to draft invoice
        $this->expectException(ValidationException::class);

        PaymentService::applyPaymentToInvoice(new CreateApplyForInvoiceDto([
            'apply_date' => now()->format('Y-m-d'),
            'applicable' => (object)[
                'id' => $payment->id,
                'customer_id' => $payment->customer_id,
            ],
            'applicable_type' => MorphablesEnum::PAYMENT->value,
            'amount_applied' => 200,
            'invoice_id' => $draftInvoice->id,
        ]));
    }

    public function test_it_validates_credit_note_application_logic()
    {
        $customer = CustomerFactory::new()->create();
        $invoice = $this->createApprovedInvoice($customer->id, 500);
        
        // Create credit note with amount 200
        $creditNote = $this->createCreditNote($customer->id, 200);

        // Apply credit note to invoice
        PaymentService::applyPaymentToInvoice(new CreateApplyForInvoiceDto([
            'apply_date' => now()->format('Y-m-d'),
            'applicable' => (object)[
                'id' => $creditNote->id,
                'customer_id' => $creditNote->customer_id,
            ],
            'applicable_type' => MorphablesEnum::CREDIT->value,
            'amount_applied' => 200,
            'invoice_id' => $invoice->id,
        ]));

        $invoice->refresh();
        $creditNote->refresh();

        // Invoice due amount should be reduced
        $this->assertEqualsDecimals(300, $invoice->invoice_due_amount);
        
        // Credit note should be fully applied (due amount becomes 0)
        $this->assertEqualsDecimals(0, $creditNote->abs_invoice_due_amount);
    }

    public function test_it_handles_overpayment_scenarios()
    {
        $customer = CustomerFactory::new()->create();
        $invoice = $this->createApprovedInvoice($customer->id, 100);
        $payment = $this->createCustomerPayment($customer->id, 150);

        // Apply payment that would overpay the invoice
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage(__('validation-custom-finance-invoice-amount-exceeded'));

        PaymentService::applyPaymentToInvoice(new CreateApplyForInvoiceDto([
            'apply_date' => now()->format('Y-m-d'),
            'applicable' => (object)[
                'id' => $payment->id,
                'customer_id' => $payment->customer_id,
            ],
            'applicable_type' => MorphablesEnum::PAYMENT->value,
            'amount_applied' => 120, // More than invoice due amount
            'invoice_id' => $invoice->id,
        ]));
    }

    public function test_it_validates_precision_in_decimal_calculations()
    {
        $customer = CustomerFactory::new()->create();
        $payment = $this->createCustomerPayment($customer->id, 100.33);
        $invoice = $this->createApprovedInvoice($customer->id, 200.55);

        // Apply payment with precise decimal
        PaymentService::applyPaymentToInvoice(new CreateApplyForInvoiceDto([
            'apply_date' => now()->format('Y-m-d'),
            'applicable' => (object)[
                'id' => $payment->id,
                'customer_id' => $payment->customer_id,
            ],
            'applicable_type' => MorphablesEnum::PAYMENT->value,
            'amount_applied' => 100.33,
            'invoice_id' => $invoice->id,
        ]));

        $payment->refresh();
        $invoice->refresh();

        // Verify precise calculations
        $this->assertEqualsDecimals(0, $payment->amount_left);
        $this->assertEqualsDecimals(100.22, $invoice->invoice_due_amount); // 200.55 - 100.33
    }

    public function test_it_validates_concurrent_application_scenarios()
    {
        $customer = CustomerFactory::new()->create();
        $payment = $this->createCustomerPayment($customer->id, 100);
        $invoice1 = $this->createApprovedInvoice($customer->id, 80);
        $invoice2 = $this->createApprovedInvoice($customer->id, 60);

        // First application
        PaymentService::applyPaymentToInvoice(new CreateApplyForInvoiceDto([
            'apply_date' => now()->format('Y-m-d'),
            'applicable' => (object)[
                'id' => $payment->id,
                'customer_id' => $payment->customer_id,
            ],
            'applicable_type' => MorphablesEnum::PAYMENT->value,
            'amount_applied' => 70,
            'invoice_id' => $invoice1->id,
        ]));

        // Second application should succeed (30 remaining)
        PaymentService::applyPaymentToInvoice(new CreateApplyForInvoiceDto([
            'apply_date' => now()->format('Y-m-d'),
            'applicable' => (object)[
                'id' => $payment->id,
                'customer_id' => $payment->customer_id,
            ],
            'applicable_type' => MorphablesEnum::PAYMENT->value,
            'amount_applied' => 30,
            'invoice_id' => $invoice2->id,
        ]));

        $payment->refresh();
        $this->assertEqualsDecimals(0, $payment->amount_left);
    }

    // Helper methods

    private function createApprovedInvoice($customerId, $amount = null): Invoice
    {
        $customer = CustomerModel::find($customerId);
        $unitPrice = $amount ?? $this->faker->randomFloat(2, 100, 1000);

        $invoice = InvoiceService::createInvoice(new CreateInvoiceDto([
            'customer_id' => $customer->id,
            'invoice_type_id' => InvoiceTypeEnum::getEnumCase('INVOICE')->value,
            'payment_method_id' => PaymentMethodEnum::getEnumCase('CASH')->value,
            'invoice_date' => now()->format('Y-m-d'),
            'invoice_due_date' => now()->addDays(30),
            'is_draft' => false,
            'invoiceDetails' => [
                [
                    'name' => 'Test Item',
                    'description' => 'Test Description',
                    'quantity' => 1,
                    'unit_price' => $unitPrice,
                    'revenue_account_id' => GlAccountFactory::new()->create()->id,
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
            'invoice_date' => now()->format('Y-m-d'),
            'invoice_due_date' => now()->addDays(30),
            'is_draft' => false,
            'invoiceDetails' => [
                [
                    'name' => 'Credit Item',
                    'description' => 'Credit Description',
                    'quantity' => 1,
                    'unit_price' => $amount,
                    'revenue_account_id' => GlAccountFactory::new()->create()->id,
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
            'payment_date' => now()->format('Y-m-d'),
        ]));
    }
}

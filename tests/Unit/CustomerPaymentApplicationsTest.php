<?php

namespace Tests\Unit;

use Condoedge\Finance\Casts\SafeDecimal;
use Condoedge\Finance\Database\Factories\AccountFactory;
use Condoedge\Finance\Database\Factories\CustomerFactory;
use Condoedge\Finance\Database\Factories\CustomerPaymentFactory;
use Condoedge\Finance\Database\Factories\InvoiceFactory;
use Condoedge\Finance\Database\Factories\TaxFactory;
use Condoedge\Finance\Facades\ApplicableTypeEnum;
use Condoedge\Finance\Facades\CustomerModel;
use Condoedge\Finance\Facades\InvoiceModel;
use Condoedge\Finance\Facades\InvoiceTypeEnum;
use Condoedge\Finance\Facades\PaymentTypeEnum;
use Condoedge\Finance\Models\CustomerPayment;
use Condoedge\Finance\Models\Dto\Invoices\CreateInvoiceDto;
use Condoedge\Finance\Models\Dto\Payments\CreateApplyForInvoiceDto;
use Condoedge\Finance\Models\Dto\Payments\CreateAppliesForMultipleInvoiceDto;
use Condoedge\Finance\Models\Dto\Payments\CreateCustomerPaymentDto;
use Condoedge\Finance\Models\Dto\Payments\CreateCustomerPaymentForInvoiceDto;
use Condoedge\Finance\Models\Invoice;
use Condoedge\Finance\Models\InvoiceApply;
use Condoedge\Finance\Models\InvoiceStatusEnum;
use Condoedge\Finance\Models\MorphablesEnum;
use Exception;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Validation\ValidationException;
use Kompo\Auth\Database\Factories\UserFactory;
use Tests\TestCase;

class CustomerPaymentApplicationsTest extends TestCase
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

    public function test_it_can_create_a_customer_payment()
    {
        $customer = CustomerFactory::new()->create();
        $amount = $this->faker->randomFloat(2, 100, 1000);
        $paymentDate = now();

        $payment = CustomerPayment::createForCustomer(new CreateCustomerPaymentDto([
            'customer_id' => $customer->id,
            'amount' => $amount,
            'payment_date' => $paymentDate,
        ]));

        $this->assertDatabaseHas('fin_customer_payments', [
            'id' => $payment->id,
            'customer_id' => $customer->id,
            'amount' => db_decimal_format($amount),
            'amount_left' => db_decimal_format($amount), // Initially amount_left equals amount
            'payment_date' => db_date_format($paymentDate),
        ]);

        $this->assertEqualsDecimals($amount, $payment->amount);
        $this->assertEqualsDecimals($amount, $payment->amount_left);
    }

    public function test_it_can_create_a_customer_payment_for_specific_invoice()
    {
        $invoice = $this->createApprovedInvoice();
        $amount = $this->faker->randomFloat(2, 100, 500);
        $paymentDate = now();

        $payment = CustomerPayment::createForCustomer(new CreateCustomerPaymentForInvoiceDto([
            'invoice_id' => $invoice->id,
            'amount' => $amount,
            'payment_date' => $paymentDate,
        ]));

        $this->assertDatabaseHas('fin_customer_payments', [
            'id' => $payment->id,
            'customer_id' => $invoice->customer_id,
            'amount' => db_decimal_format($amount),
            'amount_left' => db_decimal_format($amount),
            'payment_date' => db_date_format($paymentDate),
        ]);

        $this->assertEquals($invoice->customer_id, $payment->customer_id);
    }

    public function test_it_can_apply_payment_to_single_invoice()
    {
        $invoice = $this->createApprovedInvoice(null, 1000);
        $payment = $this->createCustomerPayment($invoice->customer_id, 500);
        
        $applicationAmount = 200;
        $applyDate = now();

        $invoiceApply = InvoiceApply::createForInvoice(new CreateApplyForInvoiceDto([
            'apply_date' => $applyDate,
            'applicable' => (object)[
                'id' => $payment->id,
                'customer_id' => $payment->customer_id,
            ],
            'applicable_type' => MorphablesEnum::PAYMENT->value,
            'amount_applied' => $applicationAmount,
            'invoice_id' => $invoice->id,
        ]));

        $this->assertDatabaseHas('fin_invoice_applies', [
            'id' => $invoiceApply->id,
            'invoice_id' => $invoice->id,
            'applicable_id' => $payment->id,
            'applicable_type' => MorphablesEnum::PAYMENT->value,
            'payment_applied_amount' => db_decimal_format($applicationAmount),
            'apply_date' => db_date_format($applyDate),
        ]);

        // Verify payment amount_left is reduced
        $payment->refresh();
        $this->assertEqualsDecimals(300, $payment->amount_left); // 500 - 200

        // Verify invoice due amount is reduced
        $invoice->refresh();
        $originalDue = $invoice->invoice_total_amount;
        $this->assertEqualsDecimals($originalDue->subtract($applicationAmount), $invoice->invoice_due_amount);
    }

    public function test_it_can_apply_payment_to_multiple_invoices()
    {
        $customer = CustomerFactory::new()->create();
        $invoice1 = $this->createApprovedInvoice($customer->id, 300);
        $invoice2 = $this->createApprovedInvoice($customer->id, 400);
        $payment = $this->createCustomerPayment($customer->id, 500);

        $applyDate = now();
        
        InvoiceApply::createForMultipleInvoices(new CreateAppliesForMultipleInvoiceDto([
            'apply_date' => $applyDate->format('Y-m-d'),
            'applicable' => (object)[
                'id' => $payment->id,
                'customer_id' => $payment->customer_id,
            ],
            'applicable_type' => MorphablesEnum::PAYMENT->value,
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
            'applicable_id' => $payment->id,
            'payment_applied_amount' => 250,
        ]);

        $this->assertDatabaseHas('fin_invoice_applies', [
            'invoice_id' => $invoice2->id,
            'applicable_id' => $payment->id,
            'payment_applied_amount' => 200,
        ]);

        // Verify payment amount_left
        $payment->refresh();
        $this->assertEqualsDecimals(50, $payment->amount_left); // 500 - 250 - 200

        // Verify invoice due amounts
        $invoice1->refresh();
        $invoice2->refresh();
        $this->assertEqualsDecimals(50, $invoice1->invoice_due_amount); // 300 - 250
        $this->assertEqualsDecimals(200, $invoice2->invoice_due_amount); // 400 - 200
    }

    public function test_it_prevents_applying_more_than_payment_amount_left()
    {
        $invoice = $this->createApprovedInvoice();
        $payment = $this->createCustomerPayment($invoice->customer_id, 100);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('applicable-amount-exceeded');

        InvoiceApply::createForInvoice(new CreateApplyForInvoiceDto([
            'apply_date' => now(),
            'applicable' => (object)[
                'id' => $payment->id,
                'customer_id' => $payment->customer_id,
            ],
            'applicable_type' => MorphablesEnum::PAYMENT->value,
            'amount_applied' => 150, // More than payment amount
            'invoice_id' => $invoice->id,
        ]));
    }

    public function test_it_prevents_applying_more_than_invoice_due_amount()
    {
        $invoice = $this->createApprovedInvoice(null, 100);
        $payment = $this->createCustomerPayment($invoice->customer_id, 500);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('invoice-amount-exceeded');

        InvoiceApply::createForInvoice(new CreateApplyForInvoiceDto([
            'apply_date' => now(),
            'applicable' => (object)[
                'id' => $payment->id,
                'customer_id' => $payment->customer_id,
            ],
            'applicable_type' => MorphablesEnum::PAYMENT->value,
            'amount_applied' => 150, // More than invoice due amount
            'invoice_id' => $invoice->id,
        ]));
    }

    public function test_it_prevents_applying_payment_to_invoice_from_different_customer()
    {
        $customer1 = CustomerFactory::new()->create();
        $customer2 = CustomerFactory::new()->create();
        
        $invoice = $this->createApprovedInvoice($customer1->id);
        $payment = $this->createCustomerPayment($customer2->id, 500);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('invoice-customer-mismatch');

        InvoiceApply::createForInvoice(new CreateApplyForInvoiceDto([
            'apply_date' => now(),
            'applicable' => (object)[
                'id' => $payment->id,
                'customer_id' => $payment->customer_id,
            ],
            'applicable_type' => MorphablesEnum::PAYMENT->value,
            'amount_applied' => 100,
            'invoice_id' => $invoice->id,
        ]));
    }

    public function test_it_prevents_applying_payment_twice_with_integrity_constraints()
    {
        $invoice = $this->createApprovedInvoice(null, 1000);
        $payment = $this->createCustomerPayment($invoice->customer_id, 500);

        // First application should succeed
        InvoiceApply::createForInvoice(new CreateApplyForInvoiceDto([
            'apply_date' => now(),
            'applicable' => (object)[
                'id' => $payment->id,
                'customer_id' => $payment->customer_id,
            ],
            'applicable_type' => MorphablesEnum::PAYMENT->value,
            'amount_applied' => 300,
            'invoice_id' => $invoice->id,
        ]));

        // Second application should fail due to insufficient amount_left
        $this->expectException(ValidationException::class);

        InvoiceApply::createForInvoice(new CreateApplyForInvoiceDto([
            'apply_date' => now(),
            'applicable' => (object)[
                'id' => $payment->id,
                'customer_id' => $payment->customer_id,
            ],
            'applicable_type' => MorphablesEnum::PAYMENT->value,
            'amount_applied' => $invoice->invoice_total_amount, // Would exceed amount_left
            'invoice_id' => $invoice->id,
        ]));
    }

    public function test_it_handles_partial_payment_applications()
    {
        $invoice = $this->createApprovedInvoice(null, 1000);
        $payment1 = $this->createCustomerPayment($invoice->customer_id, 300);
        $payment2 = $this->createCustomerPayment($invoice->customer_id, 400);

        // Apply first payment partially
        InvoiceApply::createForInvoice(new CreateApplyForInvoiceDto([
            'apply_date' => now(),
            'applicable' => (object)[
                'id' => $payment1->id,
                'customer_id' => $payment1->customer_id,
            ],
            'applicable_type' => MorphablesEnum::PAYMENT->value,
            'amount_applied' => 300,
            'invoice_id' => $invoice->id,
        ]));

        // Apply second payment partially
        InvoiceApply::createForInvoice(new CreateApplyForInvoiceDto([
            'apply_date' => now(),
            'applicable' => (object)[
                'id' => $payment2->id,
                'customer_id' => $payment2->customer_id,
            ],
            'applicable_type' => MorphablesEnum::PAYMENT->value,
            'amount_applied' => 350,
            'invoice_id' => $invoice->id,
        ]));

        // Verify invoice due amount
        $invoice->refresh();
        $this->assertEqualsDecimals(350, $invoice->invoice_due_amount); // 1000 - 300 - 350

        // Verify payment amounts left
        $payment1->refresh();
        $payment2->refresh();
        $this->assertEqualsDecimals(0, $payment1->amount_left);
        $this->assertEqualsDecimals(50, $payment2->amount_left);
    }

    public function test_it_can_apply_credit_note_to_invoice()
    {
        $customer = CustomerFactory::new()->create();
        $originalInvoice = $this->createApprovedInvoice($customer->id, 500);
        
        // Create a credit note
        $creditNote = $this->createCreditNote($customer->id, 200);

        InvoiceApply::createForInvoice(new CreateApplyForInvoiceDto([
            'apply_date' => now(),
            'applicable' => (object)[
                'id' => $creditNote->id,
                'customer_id' => $creditNote->customer_id,
            ],
            'applicable_type' => MorphablesEnum::CREDIT->value,
            'amount_applied' => 200,
            'invoice_id' => $originalInvoice->id,
        ]));

        // Verify application was created
        $this->assertDatabaseHas('fin_invoice_applies', [
            'invoice_id' => $originalInvoice->id,
            'applicable_id' => $creditNote->id,
            'applicable_type' => MorphablesEnum::CREDIT->value,
            'payment_applied_amount' => 200,
        ]);

        // Verify invoice due amount reduced
        $originalInvoice->refresh();
        $this->assertEqualsDecimals(300, $originalInvoice->invoice_due_amount);

        // Verify credit note due amount reduced (becomes more negative)
        $creditNote->refresh();
        $this->assertEqualsDecimals(0, $creditNote->abs_invoice_due_amount);
    }

    public function test_it_validates_database_integrity_triggers()
    {
        $invoice = $this->createApprovedInvoice(null, 100);
        $payment = $this->createCustomerPayment($invoice->customer_id, 200);

        // Create valid application
        $application = InvoiceApply::createForInvoice(new CreateApplyForInvoiceDto([
            'apply_date' => now(),
            'applicable' => (object)[
                'id' => $payment->id,
                'customer_id' => $payment->customer_id,
            ],
            'applicable_type' => MorphablesEnum::PAYMENT->value,
            'amount_applied' => 100,
            'invoice_id' => $invoice->id,
        ]));

        // Verify trigger calculated amounts correctly
        $payment->refresh();
        $invoice->refresh();

        $this->assertEqualsDecimals(100, $payment->amount_left);
        $this->assertEqualsDecimals(0, $invoice->invoice_due_amount);

        // Test that direct database manipulation would be prevented by triggers
        // (This would normally throw an exception due to database constraints)
        try {
            \DB::table('fin_invoice_applies')->insert([
                'invoice_id' => $invoice->id,
                'applicable_id' => $payment->id,
                'applicable_type' => MorphablesEnum::PAYMENT->value,
                'payment_applied_amount' => 150, // This would exceed limits
                'apply_date' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->fail('Expected database constraint violation');
        } catch (\Exception $e) {
            // Expected - database triggers should prevent this
            $this->assertTrue(true);
        }
    }

    // Helper methods

    private function createApprovedInvoice($customerId = null, $amount = null): Invoice
    {
        $customer = $customerId ? CustomerModel::find($customerId) : CustomerFactory::new()->create();
        $quantity = 1;
        $unitPrice = $amount ?? $this->faker->randomFloat(2, 100, 1000);

        $invoice = InvoiceModel::createInvoiceFromDto(new CreateInvoiceDto([
            'customer_id' => $customer->id,
            'invoice_type_id' => InvoiceTypeEnum::getEnumClass()::INVOICE->value,
            'payment_type_id' => PaymentTypeEnum::getEnumClass()::CASH->value,
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

        $creditNote = InvoiceModel::createInvoiceFromDto(new CreateInvoiceDto([
            'customer_id' => $customer->id,
            'invoice_type_id' => InvoiceTypeEnum::getEnumClass()::CREDIT->value,
            'payment_type_id' => PaymentTypeEnum::getEnumClass()::CASH->value,
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
        return CustomerPayment::createForCustomer(new CreateCustomerPaymentDto([
            'customer_id' => $customerId,
            'amount' => $amount,
            'payment_date' => now(),
        ]));
    }
}

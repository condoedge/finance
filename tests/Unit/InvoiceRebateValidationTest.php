<?php

namespace Tests\Unit;

use Condoedge\Finance\Database\Factories\CustomerFactory;
use Condoedge\Finance\Database\Factories\GlAccountFactory;
use Condoedge\Finance\Database\Factories\PaymentTermFactory;
use Condoedge\Finance\Facades\InvoiceService;
use Condoedge\Finance\Facades\InvoiceTypeEnum;
use Condoedge\Finance\Facades\PaymentMethodEnum;
use Condoedge\Finance\Models\Dto\Invoices\CreateInvoiceDto;
use Exception;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Validation\ValidationException;
use Kompo\Auth\Database\Factories\UserFactory;
use Tests\TestCase;

class InvoiceRebateValidationTest extends TestCase
{
    use WithFaker;

    public function setUp(): void
    {
        parent::setUp();

        /** @var \Kompo\Auth\Models\User $user */
        $user = UserFactory::new()->create()->first();
        if (!$user) {
            throw new Exception('Unknown error creating user');
        }
        $this->actingAs($user);
    }

    public function test_it_allows_negative_line_items_in_positive_total_invoice()
    {
        $customer = CustomerFactory::new()->create();

        $invoice = InvoiceService::createInvoice(new CreateInvoiceDto([
            'customer_id' => $customer->id,
            'invoice_type_id' => InvoiceTypeEnum::getEnumCase('INVOICE')->value,
            'payment_method_id' => PaymentMethodEnum::getEnumCase('CASH')->value,
            'payment_term_id' => PaymentTermFactory::new()->create()->id,
            'invoice_date' => now()->format('Y-m-d'),
            'invoiceDetails' => [
                [
                    'name' => 'Product 1',
                    'description' => 'Main product',
                    'quantity' => 2,
                    'unit_price' => 100, // +200
                    'revenue_account_id' => GlAccountFactory::new()->create()->id,
                    'taxesIds' => [],
                ],
                [
                    'name' => 'Rebate',
                    'description' => 'Customer discount',
                    'quantity' => 1,
                    'unit_price' => -50, // -50 (rebate)
                    'revenue_account_id' => GlAccountFactory::new()->create()->id,
                    'taxesIds' => [],
                ],
            ],
        ]));

        // Total should be +150 (200 - 50), which is positive for INVOICE type
        $this->assertEqualsDecimals(150, $invoice->invoice_amount_before_taxes);
        $this->assertEquals(InvoiceTypeEnum::getEnumCase('INVOICE'), $invoice->invoice_type_id);
    }

    public function test_it_prevents_negative_total_on_regular_invoice()
    {
        $customer = CustomerFactory::new()->create();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage(__('validation-invoice-total-should-be-positive'));

        InvoiceService::createInvoice(new CreateInvoiceDto([
            'customer_id' => $customer->id,
            'invoice_type_id' => InvoiceTypeEnum::getEnumCase('INVOICE')->value,
            'payment_method_id' => PaymentMethodEnum::getEnumCase('CASH')->value,
            'payment_term_id' => PaymentTermFactory::new()->create()->id,
            'invoice_date' => now()->format('Y-m-d'),
            'invoiceDetails' => [
                [
                    'name' => 'Large Rebate',
                    'description' => 'Excessive discount',
                    'quantity' => 1,
                    'unit_price' => -100, // Negative total
                    'revenue_account_id' => GlAccountFactory::new()->create()->id,
                    'taxesIds' => [],
                ],
            ],
        ]));
    }

    public function test_it_allows_negative_total_on_credit_note()
    {
        $customer = CustomerFactory::new()->create();

        $creditNote = InvoiceService::createInvoice(new CreateInvoiceDto([
            'customer_id' => $customer->id,
            'invoice_type_id' => InvoiceTypeEnum::getEnumCase('CREDIT')->value,
            'payment_method_id' => PaymentMethodEnum::getEnumCase('CASH')->value,
            'payment_term_id' => PaymentTermFactory::new()->create()->id,
            'invoice_date' => now()->format('Y-m-d'),
            'invoiceDetails' => [
                [
                    'name' => 'Credit Item',
                    'description' => 'Refund',
                    'quantity' => 1,
                    'unit_price' => -200, // Negative amount for credit
                    'revenue_account_id' => GlAccountFactory::new()->create()->id,
                    'taxesIds' => [],
                ],
            ],
        ]));

        // Total should be -200, which is negative for CREDIT type
        $this->assertEqualsDecimals(-200, $creditNote->invoice_amount_before_taxes);
        $this->assertEquals(InvoiceTypeEnum::getEnumCase('CREDIT'), $creditNote->invoice_type_id);
    }

    public function test_it_prevents_positive_total_on_credit_note()
    {
        $customer = CustomerFactory::new()->create();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage(__('validation-invoice-total-should-be-negative'));

        InvoiceService::createInvoice(new CreateInvoiceDto([
            'customer_id' => $customer->id,
            'invoice_type_id' => InvoiceTypeEnum::getEnumCase('CREDIT')->value,
            'payment_method_id' => PaymentMethodEnum::getEnumCase('CASH')->value,
            'payment_term_id' => PaymentTermFactory::new()->create()->id,
            'invoice_date' => now()->format('Y-m-d'),
            'invoiceDetails' => [
                [
                    'name' => 'Positive Item',
                    'description' => 'Should not work on credit',
                    'quantity' => 1,
                    'unit_price' => 100, // Positive amount on credit note
                    'revenue_account_id' => GlAccountFactory::new()->create()->id,
                    'taxesIds' => [],
                ],
            ],
        ]));
    }

    public function test_it_allows_mixed_positive_negative_items_with_correct_total()
    {
        $customer = CustomerFactory::new()->create();

        // Test complex invoice with multiple rebates
        $invoice = InvoiceService::createInvoice(new CreateInvoiceDto([
            'customer_id' => $customer->id,
            'invoice_type_id' => InvoiceTypeEnum::getEnumCase('INVOICE')->value,
            'payment_method_id' => PaymentMethodEnum::getEnumCase('CASH')->value,
            'payment_term_id' => PaymentTermFactory::new()->create()->id,
            'invoice_date' => now()->format('Y-m-d'),
            'invoiceDetails' => [
                [
                    'name' => 'Product A',
                    'quantity' => 3,
                    'unit_price' => 100, // +300
                    'revenue_account_id' => GlAccountFactory::new()->create()->id,
                    'taxesIds' => [],
                ],
                [
                    'name' => 'Product B',
                    'quantity' => 2,
                    'unit_price' => 50, // +100
                    'revenue_account_id' => GlAccountFactory::new()->create()->id,
                    'taxesIds' => [],
                ],
                [
                    'name' => 'Volume Discount',
                    'quantity' => 1,
                    'unit_price' => -75, // -75 (rebate)
                    'revenue_account_id' => GlAccountFactory::new()->create()->id,
                    'taxesIds' => [],
                ],
                [
                    'name' => 'Loyalty Rebate',
                    'quantity' => 1,
                    'unit_price' => -25, // -25 (rebate)
                    'revenue_account_id' => GlAccountFactory::new()->create()->id,
                    'taxesIds' => [],
                ],
            ],
        ]));

        // Total: 300 + 100 - 75 - 25 = 300 (positive for INVOICE)
        $this->assertEqualsDecimals(300, $invoice->invoice_amount_before_taxes);

        // Verify individual line items preserve their signs
        $details = $invoice->invoiceDetails;
        $this->assertEqualsDecimals(300, $details[0]->extended_price); // 3 * 100
        $this->assertEqualsDecimals(100, $details[1]->extended_price); // 2 * 50
        $this->assertEqualsDecimals(-75, $details[2]->extended_price); // 1 * -75 (preserved negative)
        $this->assertEqualsDecimals(-25, $details[3]->extended_price); // 1 * -25 (preserved negative)
    }
}
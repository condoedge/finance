<?php

namespace Tests\Unit;

use Condoedge\Finance\Database\Factories\CustomerFactory;
use Condoedge\Finance\Database\Factories\GlAccountFactory;
use Condoedge\Finance\Database\Factories\PaymentTermFactory;
use Condoedge\Finance\Facades\InvoiceDetailService;
use Condoedge\Finance\Facades\InvoiceService;
use Condoedge\Finance\Facades\InvoiceTypeEnum;
use Condoedge\Finance\Facades\PaymentMethodEnum;
use Condoedge\Finance\Models\Dto\Invoices\CreateInvoiceDto;
use Condoedge\Finance\Models\Dto\Invoices\CreateOrUpdateInvoiceDetail;
use Exception;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\DB;
use Kompo\Auth\Database\Factories\UserFactory;
use Tests\TestCase;

class InvoiceDetailIntegrityTest extends TestCase
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

    public function test_database_function_preserves_negative_unit_prices()
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
                    'name' => 'Product',
                    'quantity' => 1,
                    'unit_price' => 100,
                    'revenue_account_id' => GlAccountFactory::new()->create()->id,
                    'taxesIds' => [],
                ],
                [
                    'name' => 'Rebate',
                    'quantity' => 1,
                    'unit_price' => -25, // Negative rebate
                    'revenue_account_id' => GlAccountFactory::new()->create()->id,
                    'taxesIds' => [],
                ],
            ],
        ]));

        $details = $invoice->invoiceDetails;
        $rebateDetail = $details->where('name', 'Rebate')->first();

        // Test that database function returns the raw negative value
        $dbUnitPrice = DB::selectOne('SELECT get_detail_unit_price_with_sign(?) as unit_price', [$rebateDetail->id]);
        $this->assertEqualsDecimals(-25, $dbUnitPrice->unit_price);

        // Test that extended_price is correctly calculated as negative
        $this->assertEqualsDecimals(-25, $rebateDetail->extended_price); // 1 * -25

        // Test invoice total includes the rebate correctly
        $this->assertEqualsDecimals(75, $invoice->invoice_amount_before_taxes); // 100 - 25
    }

    public function test_direct_invoice_detail_creation_preserves_negatives()
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
                    'name' => 'Initial Product',
                    'quantity' => 1,
                    'unit_price' => 200,
                    'revenue_account_id' => GlAccountFactory::new()->create()->id,
                    'taxesIds' => [],
                ],
            ],
        ]));

        // Add a rebate detail directly through service
        $rebateDetail = InvoiceDetailService::createInvoiceDetail(new CreateOrUpdateInvoiceDetail([
            'invoice_id' => $invoice->id,
            'name' => 'Customer Loyalty Rebate',
            'description' => 'Discount for loyal customer',
            'quantity' => 1,
            'unit_price' => -50, // Direct negative value
            'revenue_account_id' => GlAccountFactory::new()->create()->id,
            'taxesIds' => [],
        ]));

        // Verify the rebate detail preserves negative values
        $this->assertEqualsDecimals(-50, $rebateDetail->unit_price);
        $this->assertEqualsDecimals(-50, $rebateDetail->extended_price);

        // Verify updated invoice total
        $invoice->refresh();
        $this->assertEqualsDecimals(150, $invoice->invoice_amount_before_taxes); // 200 - 50
    }

    public function test_update_invoice_detail_preserves_negatives()
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
                    'name' => 'Product',
                    'quantity' => 1,
                    'unit_price' => 100,
                    'revenue_account_id' => GlAccountFactory::new()->create()->id,
                    'taxesIds' => [],
                ],
            ],
        ]));

        $detail = $invoice->invoiceDetails->first();

        // Update to negative (rebate)
        $updatedDetail = InvoiceDetailService::updateInvoiceDetail(new CreateOrUpdateInvoiceDetail([
            'id' => $detail->id,
            'invoice_id' => $invoice->id,
            'name' => 'Converted to Rebate',
            'description' => 'Changed from product to rebate',
            'quantity' => 1,
            'unit_price' => -75, // Convert to negative
            'revenue_account_id' => $detail->revenue_account_id,
            'taxesIds' => [],
        ]));

        $this->assertEqualsDecimals(-75, $updatedDetail->unit_price);
        $this->assertEqualsDecimals(-75, $updatedDetail->extended_price);

        $invoice->refresh();
        $this->assertEqualsDecimals(-75, $invoice->invoice_amount_before_taxes);
    }

    public function test_integrity_checker_handles_negative_values()
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
                    'name' => 'Mixed Items',
                    'quantity' => 2,
                    'unit_price' => 100, // +200
                    'revenue_account_id' => GlAccountFactory::new()->create()->id,
                    'taxesIds' => [],
                ],
                [
                    'name' => 'Volume Rebate',
                    'quantity' => 1,
                    'unit_price' => -30, // -30
                    'revenue_account_id' => GlAccountFactory::new()->create()->id,
                    'taxesIds' => [],
                ],
            ],
        ]));

        // Force integrity recalculation
        $invoice->refresh();

        // Verify integrity calculations preserve negative values
        $this->assertEqualsDecimals(170, $invoice->invoice_amount_before_taxes); // 200 - 30

        $rebateDetail = $invoice->invoiceDetails->where('name', 'Volume Rebate')->first();
        $this->assertEqualsDecimals(-30, $rebateDetail->unit_price);
        $this->assertEqualsDecimals(-30, $rebateDetail->extended_price);
    }
}
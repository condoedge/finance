<?php

namespace Tests\Unit;

use Condoedge\Finance\Database\Factories\CustomerFactory;
use Condoedge\Finance\Database\Factories\GlAccountFactory;
use Condoedge\Finance\Facades\InvoiceService;
use Condoedge\Finance\Facades\InvoiceTypeEnum;
use Condoedge\Finance\Facades\PaymentMethodEnum;
use Condoedge\Finance\Facades\PaymentService as FacadesPaymentService;
use Condoedge\Finance\Facades\PaymentTermService;
use Condoedge\Finance\Models\Dto\Invoices\CreateInvoiceDto;
use Condoedge\Finance\Models\Dto\Invoices\UpdateInvoiceDto;
use Condoedge\Finance\Models\Dto\Payments\CreateCustomerPaymentForInvoiceDto;
use Condoedge\Finance\Models\Dto\PaymentTerms\CreateOrUpdatePaymentTermDto;
use Condoedge\Finance\Models\InvoiceStatusEnum;
use Condoedge\Finance\Models\PaymentInstallPeriodStatusEnum;
use Condoedge\Finance\Models\PaymentTermTypeEnum;
use Exception;
use Illuminate\Foundation\Testing\WithFaker;
use Kompo\Auth\Database\Factories\UserFactory;
use Tests\TestCase;

class PaymentTermTest extends TestCase
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

    public function test_create_simple_product_term()
    {
        PaymentTermService::createOrUpdatePaymentTerm(new CreateOrUpdatePaymentTermDto([
            'term_type' => PaymentTermTypeEnum::COD,
            'term_name' => 'Test Cod Term',
            'term_description' => 'This is a test cod payment term.',
        ]));

        $this->assertDatabaseHas('fin_payment_terms', [
            'term_name' => 'Test Cod Term',
            'term_description' => 'This is a test cod payment term.',
            'term_type' => PaymentTermTypeEnum::COD->value,
        ]);
    }

    public function test_create_installment_payment_term()
    {
        $term = PaymentTermService::createOrUpdatePaymentTerm(new CreateOrUpdatePaymentTermDto([
            'term_type' => PaymentTermTypeEnum::INSTALLMENT,
            'term_name' => 'Test Installment Term',
            'term_description' => 'This is a test installment payment term.',
            'settings' => [
                'periods' => 3,
                'interval' => 30,
                'interval_type' => 'days',
            ],
        ]));

        $this->assertDatabaseHas('fin_payment_terms', [
            'term_name' => 'Test Installment Term',
            'term_description' => 'This is a test installment payment term.',
            'term_type' => PaymentTermTypeEnum::INSTALLMENT->value,
        ]);

        $this->assertEquals(3, $term->settings['periods']);
        $this->assertEquals(30, $term->settings['interval']);
        $this->assertEquals('days', $term->settings['interval_type']);
    }

    public function test_create_net_payment_term()
    {
        $term = PaymentTermService::createOrUpdatePaymentTerm(new CreateOrUpdatePaymentTermDto([
            'term_type' => PaymentTermTypeEnum::NET,
            'term_name' => 'Test Net Term',
            'term_description' => 'This is a test net payment term.',
            'settings' => [
                'days' => 30,
            ],
        ]));

        $this->assertDatabaseHas('fin_payment_terms', [
            'term_name' => 'Test Net Term',
            'term_description' => 'This is a test net payment term.',
            'term_type' => PaymentTermTypeEnum::NET->value,
        ]);

        $this->assertEquals(30, $term->settings['days']);
    }

    public function test_validate_settings_depending_on_payment_term_type()
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        PaymentTermService::createOrUpdatePaymentTerm(new CreateOrUpdatePaymentTermDto([
            'term_type' => PaymentTermTypeEnum::INSTALLMENT,
            'term_name' => 'Invalid Installment Term',
            'term_description' => 'This term is missing required settings.',
            // No settings provided
        ]));
    }

    // Test invoice has a correct due_date depending on payment term type
    public function test_invoice_due_date_based_on_payment_term_type()
    {
        $codPaymentTerm = PaymentTermService::createOrUpdatePaymentTerm(new CreateOrUpdatePaymentTermDto([
            'term_type' => PaymentTermTypeEnum::COD,
            'term_name' => 'Test Installment Term COD',
            'term_description' => 'This is a test installment payment term.',
            'settings' => [
                'periods' => 3,
                'interval' => 30,
                'interval_type' => 'days',
            ],
        ]));

        $net30PaymentTerm = PaymentTermService::createOrUpdatePaymentTerm(new CreateOrUpdatePaymentTermDto([
            'term_type' => PaymentTermTypeEnum::NET,
            'term_name' => 'Test Net 30 Term',
            'term_description' => 'This is a test net 30 payment term.',
            'settings' => [
                'days' => 30,
            ],
        ]));

        $installmentsPaymentTerm = PaymentTermService::createOrUpdatePaymentTerm(new CreateOrUpdatePaymentTermDto([
            'term_type' => PaymentTermTypeEnum::INSTALLMENT,
            'term_name' => 'Test Installment Term',
            'term_description' => 'This is a test installment payment term.',
            'settings' => [
                'periods' => 3,
                'interval' => 1,
                'interval_type' => 'months',
            ],
        ]));

        $invoice = InvoiceService::createInvoice(new CreateInvoiceDto([
            'customer_id' => CustomerFactory::new()->create()->id,
            'invoice_type_id' => InvoiceTypeEnum::getEnumCase('INVOICE')->value,
            'payment_method_id' => PaymentMethodEnum::getEnumCase('BANK_TRANSFER')->value,
            'payment_term_id' => $codPaymentTerm->id,
            'invoice_date' => now()->addDays(2),
            'invoiceDetails' => [
                [
                    'name' => 'Test Item',
                    'description' => 'Test Description',
                    'quantity' => 1,
                    'unit_price' => 2,
                    'revenue_account_id' => GlAccountFactory::new()->create()->id,
                    'taxesIds' => [],
                ],
            ],
        ]));

        // Assert that the invoice has the correct due date based on the payment term type
        $this->assertNotNull($invoice->invoice_due_date);
        $this->assertEquals(now()->addDays(2)->setTime(0, 0, 0), $invoice->invoice_due_date); // Assuming the first installment is due in 30 days

        InvoiceService::updateInvoice(new UpdateInvoiceDto([
            'id' => $invoice->id,
            'payment_term_id' => $net30PaymentTerm->id,
        ]));

        $invoice->refresh(); // Refresh the invoice to get the updated due date

        $this->assertNotNull($invoice->invoice_due_date);
        $this->assertEquals(now()->addDays(32)->setTime(0, 0, 0), $invoice->invoice_due_date); // Assuming net 30 means due 30 days after the invoice date

        InvoiceService::updateInvoice(new UpdateInvoiceDto([
            'id' => $invoice->id,
            'payment_term_id' => $installmentsPaymentTerm->id,
        ]));

        $invoice->refresh(); // Refresh the invoice to get the updated due date

        $this->assertNotNull($invoice->invoice_due_date);
        $this->assertEquals(now()->addMonths(2)->addDays(2)->setTime(0, 0, 0), $invoice->invoice_due_date); // The due of the invoice will be the last installment date
    }

    // Test invoice creates the right number of installments depending on payment term type
    public function test_invoice_creates_correct_number_of_installments()
    {
        $installmentsPaymentTerm = PaymentTermService::createOrUpdatePaymentTerm(new CreateOrUpdatePaymentTermDto([
            'term_type' => PaymentTermTypeEnum::INSTALLMENT,
            'term_name' => 'Test Installment Term',
            'term_description' => 'This is a test installment payment term.',
            'settings' => [
                'periods' => 3,
                'interval' => 1,
                'interval_type' => 'months',
            ],
        ]));

        $invoice = InvoiceService::createInvoice(new CreateInvoiceDto([
            'customer_id' => CustomerFactory::new()->create()->id,
            'invoice_type_id' => InvoiceTypeEnum::getEnumCase('INVOICE')->value,
            'payment_method_id' => PaymentMethodEnum::getEnumCase('BANK_TRANSFER')->value,
            'payment_term_id' => $installmentsPaymentTerm->id,
            'invoice_date' => now()->subDays(2),
            'invoiceDetails' => [
                [
                    'name' => 'Test Item',
                    'description' => 'Test Description',
                    'quantity' => 1,
                    'unit_price' => 3,
                    'revenue_account_id' => GlAccountFactory::new()->create()->id,
                    'taxesIds' => [],
                ],
            ],
        ]));

        $this->assertCount(3, $invoice->installmentsPeriods);

        // Checking due dates of installments
        $this->assertEquals(now()->subDays(2)->setTime(0, 0, 0), $invoice->installmentsPeriods[0]->due_date);
        $this->assertEquals(now()->subDays(2)->addMonths(1)->setTime(0, 0, 0), $invoice->installmentsPeriods[1]->due_date);
        $this->assertEquals(now()->subDays(2)->addMonths(2)->setTime(0, 0, 0), $invoice->installmentsPeriods[2]->due_date);

        // Check the amounts
        $this->assertEqualsDecimals(1, $invoice->installmentsPeriods[0]->amount);
        $this->assertEqualsDecimals(1, $invoice->installmentsPeriods[1]->amount);
        $this->assertEqualsDecimals(1, $invoice->installmentsPeriods[2]->amount);

        // Check the status of installments
        $this->assertEquals(PaymentInstallPeriodStatusEnum::OVERDUE, $invoice->installmentsPeriods[0]->status);
        $this->assertEquals(PaymentInstallPeriodStatusEnum::PENDING, $invoice->installmentsPeriods[1]->status);
        $this->assertEquals(PaymentInstallPeriodStatusEnum::PENDING, $invoice->installmentsPeriods[2]->status);
    }

    // The rounding in installments is working well on big decimals
    public function test_installments_rounding_on_big_decimals()
    {
        $installmentsPaymentTerm = PaymentTermService::createOrUpdatePaymentTerm(new CreateOrUpdatePaymentTermDto([
            'term_type' => PaymentTermTypeEnum::INSTALLMENT,
            'term_name' => 'Test Installment Term',
            'term_description' => 'This is a test installment payment term.',
            'settings' => [
                'periods' => 3,
                'interval' => 1,
                'interval_type' => 'months',
            ],
        ]));

        $invoice = InvoiceService::createInvoice(new CreateInvoiceDto([
            'customer_id' => CustomerFactory::new()->create()->id,
            'invoice_type_id' => InvoiceTypeEnum::getEnumCase('INVOICE')->value,
            'payment_method_id' => PaymentMethodEnum::getEnumCase('BANK_TRANSFER')->value,
            'payment_term_id' => $installmentsPaymentTerm->id,
            'invoice_date' => now()->subDays(2),
            'invoiceDetails' => [
                [
                    'name' => 'Test Item',
                    'description' => 'Test Description',
                    'quantity' => 1,
                    'unit_price' => 7, // Diving it by 3 will give us a periodic amount of 2.3333333333333333
                    'revenue_account_id' => GlAccountFactory::new()->create()->id,
                    'taxesIds' => [],
                ],
            ],
        ]));

        $this->assertCount(3, $invoice->installmentsPeriods);

        // Set the precision config
        config(['kompo-finance.decimal-scale' => 5]);

        // Check the amounts. The first one will be 2.33334 to avoid rounding issues
        $this->assertEqualsDecimals(2.33334, $invoice->installmentsPeriods[0]->amount);
        $this->assertEqualsDecimals(2.33333, $invoice->installmentsPeriods[1]->amount);
        $this->assertEqualsDecimals(2.33333, $invoice->installmentsPeriods[2]->amount);

        $this->assertEqualsDecimals(7, $invoice->installmentsPeriods->sumDecimals('amount'));
    }

    // Changing invoice term id clean up old installments
    public function test_changing_invoice_payment_term_cleans_up_old_installments()
    {
        $installmentsPaymentTerm = PaymentTermService::createOrUpdatePaymentTerm(new CreateOrUpdatePaymentTermDto([
            'term_type' => PaymentTermTypeEnum::INSTALLMENT,
            'term_name' => 'Test Installment Term',
            'term_description' => 'This is a test installment payment term.',
            'settings' => [
                'periods' => 3,
                'interval' => 1,
                'interval_type' => 'months',
            ],
        ]));

        $invoice = InvoiceService::createInvoice(new CreateInvoiceDto([
            'customer_id' => CustomerFactory::new()->create()->id,
            'invoice_type_id' => InvoiceTypeEnum::getEnumCase('INVOICE')->value,
            'payment_method_id' => PaymentMethodEnum::getEnumCase('BANK_TRANSFER')->value,
            'payment_term_id' => $installmentsPaymentTerm->id,
            'invoice_date' => now()->subDays(2),
            'invoiceDetails' => [
                [
                    'name' => 'Test Item',
                    'description' => 'Test Description',
                    'quantity' => 1,
                    'unit_price' => 3,
                    'revenue_account_id' => GlAccountFactory::new()->create()->id,
                    'taxesIds' => [],
                ],
            ],
        ]));

        $this->assertCount(3, $invoice->installmentsPeriods);

        // Change the payment term to COD
        $codPaymentTerm = PaymentTermService::createOrUpdatePaymentTerm(new CreateOrUpdatePaymentTermDto([
            'term_type' => PaymentTermTypeEnum::COD,
            'term_name' => 'Test COD Term',
            'term_description' => 'This is a test COD payment term.',
        ]));

        InvoiceService::updateInvoice(new UpdateInvoiceDto([
            'id' => $invoice->id,
            'payment_term_id' => $codPaymentTerm->id,
        ]));

        // Assert that the old installments are cleaned up
        $invoice->refresh();
        $this->assertCount(0, $invoice->installmentsPeriods);
    }

    // Test payment status are updated when it got paid
    public function test_payment_installment_status_is_updated_when_paid()
    {
        $installmentsPaymentTerm = PaymentTermService::createOrUpdatePaymentTerm(new CreateOrUpdatePaymentTermDto([
            'term_type' => PaymentTermTypeEnum::INSTALLMENT,
            'term_name' => 'Test Installment Term',
            'term_description' => 'This is a test installment payment term.',
            'settings' => [
                'periods' => 3,
                'interval' => 1,
                'interval_type' => 'months',
            ],
        ]));

        $invoice = InvoiceService::createInvoice(new CreateInvoiceDto([
            'customer_id' => CustomerFactory::new()->create()->id,
            'invoice_type_id' => InvoiceTypeEnum::getEnumCase('INVOICE')->value,
            'payment_method_id' => PaymentMethodEnum::getEnumCase('BANK_TRANSFER')->value,
            'payment_term_id' => $installmentsPaymentTerm->id,
            'invoice_date' => now()->subDays(2),
            'is_draft' => false,
            'invoiceDetails' => [
                [
                    'name' => 'Test Item',
                    'description' => 'Test Description',
                    'quantity' => 1,
                    'unit_price' => 3,
                    'revenue_account_id' => GlAccountFactory::new()->create()->id,
                    'taxesIds' => [],
                ],
            ],
        ]));


        $this->assertEquals(InvoiceStatusEnum::OVERDUE, $invoice->invoice_status_id);

        $this->assertEquals(PaymentInstallPeriodStatusEnum::OVERDUE, $invoice->installmentsPeriods[0]->status);
        $this->assertEquals(PaymentInstallPeriodStatusEnum::PENDING, $invoice->installmentsPeriods[1]->status);
        $this->assertEquals(PaymentInstallPeriodStatusEnum::PENDING, $invoice->installmentsPeriods[2]->status);

        FacadesPaymentService::createPaymentAndApplyToInvoice(new CreateCustomerPaymentForInvoiceDto([
            'invoice_id' => $invoice->id,
            'payment_date' => now()->subDays(1),
            'amount' => 1.5,
        ]));

        $invoice->refresh();

        $this->assertEquals(InvoiceStatusEnum::PARTIAL, $invoice->invoice_status_id);

        $this->assertEquals(PaymentInstallPeriodStatusEnum::PAID, $invoice->installmentsPeriods[0]->status);
        $this->assertEquals(PaymentInstallPeriodStatusEnum::PENDING, $invoice->installmentsPeriods[1]->status);
        $this->assertEquals(PaymentInstallPeriodStatusEnum::PENDING, $invoice->installmentsPeriods[2]->status);

        FacadesPaymentService::createPaymentAndApplyToInvoice(new CreateCustomerPaymentForInvoiceDto([
            'invoice_id' => $invoice->id,
            'payment_date' => now(),
            'amount' => 1.5,
        ]));

        $invoice->refresh();

        $this->assertEquals(InvoiceStatusEnum::PAID, $invoice->invoice_status_id);

        $this->assertEquals(PaymentInstallPeriodStatusEnum::PAID, $invoice->installmentsPeriods[0]->status);
        $this->assertEquals(PaymentInstallPeriodStatusEnum::PAID, $invoice->installmentsPeriods[1]->status);
        $this->assertEquals(PaymentInstallPeriodStatusEnum::PAID, $invoice->installmentsPeriods[2]->status);
    }
}

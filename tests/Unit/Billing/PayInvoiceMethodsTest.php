<?php

namespace Tests\Unit\Billing;

use Condoedge\Finance\Database\Factories\PaymentTermFactory;
use Condoedge\Finance\Facades\InvoiceService;
use Condoedge\Finance\Facades\PaymentGateway;
use Condoedge\Finance\Models\Dto\Invoices\PayInvoiceDto;
use Condoedge\Finance\Models\Invoice;
use Condoedge\Finance\Models\InvoiceStatusEnum;
use Condoedge\Finance\Models\InvoiceTypeEnum;
use Condoedge\Finance\Models\PaymentInstallmentPeriod;
use Condoedge\Finance\Models\PaymentInstallPeriodStatusEnum;
use Condoedge\Finance\Models\PaymentMethodEnum;
use Condoedge\Finance\Models\PaymentTermTypeEnum;
use Illuminate\Http\Request;
use Tests\Mocks\MockPaymentProvider;

/**
 * Test the new payInvoice methods and payment resolvers
 *
 * This test class focuses on testing the new payment functionality
 * including the payInvoice method implementations and resolver patterns.
 */
class PayInvoiceMethodsTest extends PaymentTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        // Mock the request for payment data
        $this->mockRequest();
    }

    /**
     * Test paying invoice through new payInvoice method
     */
    public function test_pay_invoice_method_processes_payment_successfully()
    {
        $invoice = $this->createTestInvoice(750, PaymentMethodEnum::CREDIT_CARD);

        // Mock the payment gateway
        $mockGateway = new MockPaymentProvider();
        $mockGateway->initializeContext(['invoice' => $invoice]);
        $mockGateway->setShouldSucceed(true);
        $mockGateway->setResponseData([
            'amount' => 750,
            'referenceUUID' => 'PAYINVOICE-001',
        ]);

        // Mock the PaymentGateway facade
        PaymentGateway::shouldReceive('getGatewayForInvoice')
            ->once()
            ->withArgs(function ($invoiceArg, $contextArg) use ($invoice) {
                // Verify the invoice matches and context is an array
                return $invoiceArg->id === $invoice->id &&
                       is_array($contextArg) &&
                       array_key_exists('installment_ids', $contextArg);
            })
            ->andReturn($mockGateway);

        // Create DTO for payment
        $dto = new PayInvoiceDto([
            'invoice_id' => $invoice->id,
            'payment_method_id' => PaymentMethodEnum::CREDIT_CARD->value,
            'payment_term_id' => $invoice->payment_term_id,
            'request_data' => $this->createTestPaymentRequest(750),
        ]);

        // Pay invoice using the service method
        $result = InvoiceService::payInvoice($dto);

        $this->assertTrue($result);

        // Verify invoice is paid
        $invoice->refresh();
        $this->assertEqualsDecimals(0, $invoice->invoice_due_amount);
        $this->assertEquals(InvoiceStatusEnum::PAID->value, $invoice->invoice_status_id->value);
    }

    /**
     * Test paying invoice with installments
     */
    public function test_pay_invoice_with_installments_selection()
    {
        $invoice = $this->createTestInvoice(1500, PaymentMethodEnum::CREDIT_CARD, true);
        $invoice->payment_term_id = PaymentTermFactory::new()->create([
            'term_type' => PaymentTermTypeEnum::INSTALLMENT,
            'settings' => [
                'interval' => 1,
                'periods' => 3,
                'interval_type' => 'months',
            ]
        ])->id;
        $invoice->save();

        $invoice->markApproved();

        $installments = $invoice->installmentsPeriods;

        // Mock payment gateway
        $mockGateway = new MockPaymentProvider();
        $mockGateway->initializeContext([
            'invoice' => $invoice,
            'installment_ids' => [$installments[0]->id, $installments[1]->id],
        ]);
        $mockGateway->setShouldSucceed(true);
        $mockGateway->setResponseData([
            'amount' => 1000,
            'referenceUUID' => 'PAYINVOICE-INST-001',
        ]);

        PaymentGateway::shouldReceive('getGatewayForInvoice')
            ->once()
            ->andReturn($mockGateway);

        // Set request data with installments
        $this->mockRequestData($this->createTestPaymentRequest(1000, [
            'installment_ids' => [$installments[0]->id, $installments[1]->id],
        ]));

        // Create DTO with installment IDs
        $dto = new PayInvoiceDto([
            'invoice_id' => $invoice->id,
            'payment_method_id' => PaymentMethodEnum::CREDIT_CARD->value,
            'payment_term_id' => $invoice->payment_term_id,
            'installment_ids' => [$installments[0]->id, $installments[1]->id],
        ]);

        $result = InvoiceService::payInvoice($dto);

        $this->assertTrue($result);

        // Verify partial payment
        $invoice->refresh();
        $this->assertEqualsDecimals(500, $invoice->invoice_due_amount);
        $this->assertEquals(InvoiceStatusEnum::PARTIAL->value, $invoice->invoice_status_id->value);
    }

    /**
     * Test payment failure handling in payInvoice
     */
    public function test_pay_invoice_handles_payment_failure()
    {
        $invoice = $this->createTestInvoice(500, PaymentMethodEnum::CREDIT_CARD);

        // Mock failed payment
        $mockGateway = new MockPaymentProvider();
        $mockGateway->initializeContext(['invoice' => $invoice]);
        $mockGateway->setShouldSucceed(false);
        $mockGateway->setResponseData([
            'errorCode' => 'CARD_DECLINED',
            'errorMessage' => 'Card was declined by issuer',
        ]);

        PaymentGateway::shouldReceive('getGatewayForInvoice')
            ->once()
            ->andReturn($mockGateway);

        $this->mockRequestData($this->createTestPaymentRequest(500));

        $dto = new PayInvoiceDto([
            'invoice_id' => $invoice->id,
            'payment_method_id' => PaymentMethodEnum::CREDIT_CARD->value,
            'payment_term_id' => $invoice->payment_term_id,
        ]);

        try {
            $result = InvoiceService::payInvoice($dto);
            $this->fail('Expected exception for failed payment');
        } catch (\Exception $e) {
            $this->assertStringContainsString(__('error-payment-failed'), $e->getMessage());
        }

        // Verify invoice remains unpaid
        $invoice->refresh();
        $this->assertEqualsDecimals(500, $invoice->invoice_due_amount);
        $this->assertEquals(InvoiceStatusEnum::OVERDUE->value, $invoice->invoice_status_id->value);
    }

    /**
     * Test automatic approval of draft invoice during payment
     */
    public function test_pay_invoice_approves_draft_invoice_automatically()
    {
        // Create draft invoice
        $invoice = Invoice::factory()->create([
            'payment_method_id' => PaymentMethodEnum::CREDIT_CARD,
            'payment_term_id' => PaymentTermFactory::new()->create()->id,
            'invoice_due_amount' => 400,
            'invoice_type_id' => InvoiceTypeEnum::INVOICE->value,
        ]);

        // Create invoice details
        \Condoedge\Finance\Models\InvoiceDetail::factory()->create([
            'invoice_id' => $invoice->id,
            'quantity' => 1,
            'unit_price' => 400,
        ]);

        $invoice->refresh();

        // Mock gateway
        $mockGateway = new MockPaymentProvider();
        $mockGateway->initializeContext(['invoice' => $invoice]);
        $mockGateway->setShouldSucceed(true);
        $mockGateway->setResponseData(['amount' => 400]);

        PaymentGateway::shouldReceive('getGatewayForInvoice')
            ->once()
            ->andReturn($mockGateway);

        $this->mockRequestData($this->createTestPaymentRequest(400));

        $dto = new PayInvoiceDto([
            'invoice_id' => $invoice->id,
            'payment_method_id' => PaymentMethodEnum::CREDIT_CARD->value,
            'payment_term_id' => $invoice->payment_term_id,
        ]);

        $result = InvoiceService::payInvoice($dto);

        $this->assertTrue($result);

        // Verify invoice was approved and paid
        $invoice->refresh();
        $this->assertEquals(0, $invoice->is_draft);
        $this->assertNotNull($invoice->approved_at);
        $this->assertNotNull($invoice->approved_by);
        $this->assertEqualsDecimals(0, $invoice->invoice_due_amount);
    }

    /**
     * Test payment gateway context includes installments
     */
    public function test_payment_gateway_receives_installment_context()
    {
        $invoice = $this->createTestInvoice(900, PaymentMethodEnum::CREDIT_CARD, true);
        $invoice->payment_term_id = PaymentTermFactory::new()->create([
            'term_type' => PaymentTermTypeEnum::INSTALLMENT,
            'settings' => [
                'interval' => 1,
                'periods' => 3,
                'interval_type' => 'months',
            ]
        ])->id;
        $invoice->save();

        $invoice->markApproved();

        // Mock gateway to capture context
        $mockGateway = new MockPaymentProvider();
        $mockGateway->setShouldSucceed(true);
        $mockGateway->setResponseData(['amount' => 600]);
        $mockGateway->initializeContext(['invoice' => $invoice]);

        PaymentGateway::shouldReceive('getGatewayForInvoice')
            ->once()
            ->withArgs(function ($invoiceArg, $contextArg) use ($invoice) {
                // Verify the invoice matches and context is an array
                return $invoiceArg->id === $invoice->id &&
                       is_array($contextArg);
            })
            ->andReturn($mockGateway);

        $this->mockRequestData($this->createTestPaymentRequest(600));

        $installments = $invoice->installmentsPeriods;

        $dto = new PayInvoiceDto([
            'invoice_id' => $invoice->id,
            'payment_method_id' => PaymentMethodEnum::CREDIT_CARD->value,
            'payment_term_id' => $invoice->payment_term_id,
            'installment_ids' => [$installments[0]->id, $installments[1]->id],
        ]);

        InvoiceService::payInvoice($dto);

        $this->assertEquals(InvoiceStatusEnum::PARTIAL, $invoice->invoice_status_id);
        $this->assertEqualsDecimals(300, $invoice->invoice_due_amount);

        $installments = $invoice->installmentsPeriods()->get();

        $this->assertEquals(PaymentInstallPeriodStatusEnum::PAID, $installments[0]->status);
        $this->assertEquals(PaymentInstallPeriodStatusEnum::PAID, $installments[1]->status);
        $this->assertEquals(PaymentInstallPeriodStatusEnum::PENDING, $installments[2]->status);
    }

    // Helper methods

    /**
     * Mock the request facade
     */
    private function mockRequest(): void
    {
        $this->app->bind('request', function () {
            return new Request();
        });
    }

    /**
     * Mock request data
     */
    private function mockRequestData(array $data): void
    {
        request()->merge($data);
    }

    /**
     * Create installments for an invoice
     */
    private function createInstallmentsForInvoice(Invoice $invoice, int $count, float $amountEach): array
    {
        $installments = [];

        for ($i = 1; $i <= $count; $i++) {
            $installment = new PaymentInstallmentPeriod();
            $installment->invoice_id = $invoice->id;
            $installment->installment_number = $i;
            $installment->amount = $amountEach;
            $installment->due_amount = $amountEach;
            $installment->due_date = now()->addMonths($i - 1);
            $installment->save();
            $installments[] = $installment;
        }

        return $installments;
    }
}

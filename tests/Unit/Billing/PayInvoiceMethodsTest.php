<?php

namespace Tests\Unit\Billing;

use Condoedge\Finance\Billing\Core\PaymentContext;
use Condoedge\Finance\Billing\Core\PaymentProviderRegistry;
use Condoedge\Finance\Billing\Core\PaymentResult;
use Condoedge\Finance\Billing\Exceptions\PaymentProcessingException;
use Condoedge\Finance\Database\Factories\PaymentTermFactory;
use Condoedge\Finance\Facades\InvoiceService;
use Condoedge\Finance\Facades\PaymentGatewayResolver;
use Condoedge\Finance\Facades\PaymentTermService;
use Condoedge\Finance\Models\Dto\Invoices\PayInvoiceDto;
use Condoedge\Finance\Models\Invoice;
use Condoedge\Finance\Models\InvoiceStatusEnum;
use Condoedge\Finance\Models\InvoiceTypeEnum;
use Condoedge\Finance\Models\PaymentInstallmentPeriod;
use Condoedge\Finance\Models\PaymentInstallPeriodStatusEnum;
use Condoedge\Finance\Models\PaymentMethodEnum;
use Condoedge\Finance\Models\PaymentTermTypeEnum;
use Illuminate\Http\Request;
use Tests\Mocks\MockPaymentGateway;

/**
 * Test the new payInvoice methods and payment resolvers
 *
 * This test class focuses on testing the new payment functionality
 * including the payInvoice method implementations and resolver patterns.
 */
class PayInvoiceMethodsTest extends PaymentTestCase
{
    private MockPaymentGateway $mockGateway;

    public function setUp(): void
    {
        parent::setUp();

        // Create and register mock gateway
        $this->mockGateway = new MockPaymentGateway();

        // Register mock gateway in the registry
        $registry = app(PaymentProviderRegistry::class);
        $registry->register($this->mockGateway);

        // Mock the request for payment data
        $this->mockRequest();
    }

    /**
     * Test paying invoice through new payInvoice method
     */
    public function test_pay_invoice_method_processes_payment_successfully()
    {
        $invoice = $this->createTestInvoice(750, PaymentMethodEnum::CREDIT_CARD);

        // Configure mock gateway
        $this->mockGateway->reset();
        $this->mockGateway->setShouldSucceed(true);
        $this->mockGateway->setResponseData([
            'amount' => 750,
            'transactionId' => 'PAYINVOICE-001',
        ]);

        // Mock the PaymentGatewayResolver to return our mock gateway
        PaymentGatewayResolver::shouldReceive('resolve')
            ->once()
            ->withArgs(function (PaymentContext $context) use ($invoice) {
                // Verify the context has correct payable and payment method
                return $context->payable->getPayableId() === $invoice->id &&
                       $context->paymentMethod === PaymentMethodEnum::CREDIT_CARD;
            })
            ->andReturn($this->mockGateway);

        // Mock request data
        $this->mockRequestData($this->createTestPaymentRequest(750));

        // Create DTO for payment
        $dto = new PayInvoiceDto([
            'invoice_id' => $invoice->id,
            'payment_method_id' => PaymentMethodEnum::CREDIT_CARD->value,
            'payment_term_id' => $invoice->payment_term_id,
        ]);

        // Pay invoice using the service method
        $result = InvoiceService::payInvoice($dto);

        // Assert result is a PaymentResult
        $this->assertInstanceOf(PaymentResult::class, $result);
        $this->assertTrue($result->isSuccessful());
        $this->assertEquals(750, $result->amount);
        $this->assertEquals('PAYINVOICE-001', $result->transactionId);

        // Verify invoice is paid
        $invoice->refresh();
        $this->assertEqualsDecimals(0, $invoice->invoice_due_amount);
        $this->assertEquals(InvoiceStatusEnum::PAID->value, $invoice->invoice_status_id->value);

        // Verify the gateway was called
        $this->assertEquals(1, $this->mockGateway->getProcessCallCount());
    }

    /**
     * Test paying invoice with single installment
     */
    public function test_pay_invoice_with_single_installment()
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

        PaymentTermService::manageNewPaymentTermIntoInvoice($invoice);

        $installments = $invoice->installmentsPeriods;
        $firstInstallment = $installments->first();

        // Configure mock gateway
        $this->mockGateway->reset();
        $this->mockGateway->setShouldSucceed(true);
        $this->mockGateway->setResponseData([
            'amount' => 500, // First installment amount
            'transactionId' => 'PAYINVOICE-INST-001',
        ]);

        PaymentGatewayResolver::shouldReceive('resolve')
            ->once()
            ->withArgs(function (PaymentContext $context) use ($firstInstallment) {
                // When paying installment, the payable should be the installment
                return $context->payable instanceof PaymentInstallmentPeriod &&
                       $context->payable->id === $firstInstallment->id;
            })
            ->andReturn($this->mockGateway);

        // Mock request data
        $this->mockRequestData($this->createTestPaymentRequest(500));

        // Create DTO with single installment ID
        $dto = new PayInvoiceDto([
            'invoice_id' => $invoice->id,
            'payment_method_id' => PaymentMethodEnum::CREDIT_CARD->value,
            'payment_term_id' => $invoice->payment_term_id,
            'installment_id' => $firstInstallment->id,
        ]);

        $result = InvoiceService::payInvoice($dto);

        $this->assertInstanceOf(PaymentResult::class, $result);
        $this->assertTrue($result->isSuccessful());
        $this->assertEquals(500, $result->amount);

        // Verify partial payment
        $invoice->refresh();
        $this->assertEqualsDecimals(1000, $invoice->invoice_due_amount);
        $this->assertEquals(InvoiceStatusEnum::PARTIAL->value, $invoice->invoice_status_id->value);

        // Verify installment status
        $installments = $invoice->installmentsPeriods()->get();
        $this->assertEquals(PaymentInstallPeriodStatusEnum::PAID, $installments[0]->status);
        $this->assertEquals(PaymentInstallPeriodStatusEnum::PENDING, $installments[1]->status);
        $this->assertEquals(PaymentInstallPeriodStatusEnum::PENDING, $installments[2]->status);
    }

    /**
     * Test paying next installment automatically
     */
    public function test_pay_invoice_with_next_installment_flag()
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

        PaymentTermService::manageNewPaymentTermIntoInvoice($invoice);

        $installments = $invoice->installmentsPeriods;
        $firstInstallment = $installments->first();

        // Configure mock gateway
        $this->mockGateway->reset();
        $this->mockGateway->setShouldSucceed(true);
        $this->mockGateway->setResponseData([
            'amount' => 300,
            'transactionId' => 'PAYINVOICE-NEXT-001',
        ]);

        PaymentGatewayResolver::shouldReceive('resolve')
            ->once()
            ->withArgs(function (PaymentContext $context) use ($firstInstallment) {
                // Should automatically select the first unpaid installment
                return $context->payable instanceof PaymentInstallmentPeriod &&
                       $context->payable->id === $firstInstallment->id;
            })
            ->andReturn($this->mockGateway);

        $this->mockRequestData($this->createTestPaymentRequest(300));

        // Create DTO with pay_next_installment flag
        $dto = new PayInvoiceDto([
            'invoice_id' => $invoice->id,
            'payment_method_id' => PaymentMethodEnum::CREDIT_CARD->value,
            'payment_term_id' => $invoice->payment_term_id,
            'pay_next_installment' => true, // This should auto-select first unpaid installment
        ]);

        $result = InvoiceService::payInvoice($dto);

        $this->assertInstanceOf(PaymentResult::class, $result);
        $this->assertTrue($result->isSuccessful());
        $this->assertEquals(300, $result->amount);

        // Verify first installment is paid
        $installments = $invoice->installmentsPeriods()->get();
        $this->assertEquals(PaymentInstallPeriodStatusEnum::PAID, $installments[0]->status);
    }

    /**
     * Test payment failure handling in payInvoice
     */
    public function test_pay_invoice_handles_payment_failure()
    {
        $invoice = $this->createTestInvoice(500, PaymentMethodEnum::CREDIT_CARD);

        // Configure mock for failure
        $this->mockGateway->reset();
        $this->mockGateway->setShouldSucceed(false);
        $this->mockGateway->setErrorMessage('Card was declined by issuer');
        $this->mockGateway->setResponseData([
            'errorCode' => 'CARD_DECLINED',
            'transactionId' => 'FAILED-001',
        ]);

        PaymentGatewayResolver::shouldReceive('resolve')
            ->once()
            ->andReturn($this->mockGateway);

        $this->mockRequestData($this->createTestPaymentRequest(500));

        $dto = new PayInvoiceDto([
            'invoice_id' => $invoice->id,
            'payment_method_id' => PaymentMethodEnum::CREDIT_CARD->value,
            'payment_term_id' => $invoice->payment_term_id,
        ]);

        try {
            $result = InvoiceService::payInvoice($dto);

            // The result should be a failed PaymentResult
            $this->assertInstanceOf(PaymentResult::class, $result);
            $this->assertFalse($result->isSuccessful());
            $this->assertEquals('Card was declined by issuer', $result->errorMessage);
        } catch (PaymentProcessingException $e) {
            // Or it might throw an exception depending on implementation
            $this->assertStringContainsString('payment', $e->getMessage());
        }

        // Verify invoice remains unpaid
        $invoice->refresh();
        $this->assertEqualsDecimals(500, $invoice->invoice_due_amount);
        $this->assertNotEquals(InvoiceStatusEnum::PAID->value, $invoice->invoice_status_id->value);
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

        // Configure mock gateway
        $this->mockGateway->reset();
        $this->mockGateway->setShouldSucceed(true);
        $this->mockGateway->setResponseData([
            'amount' => 400,
            'transactionId' => 'AUTO-APPROVE-001',
        ]);

        PaymentGatewayResolver::shouldReceive('resolve')
            ->once()
            ->andReturn($this->mockGateway);

        $this->mockRequestData($this->createTestPaymentRequest(400));

        $dto = new PayInvoiceDto([
            'invoice_id' => $invoice->id,
            'payment_method_id' => PaymentMethodEnum::CREDIT_CARD->value,
            'payment_term_id' => $invoice->payment_term_id,
        ]);

        $result = InvoiceService::payInvoice($dto);

        $this->assertInstanceOf(PaymentResult::class, $result);
        $this->assertTrue($result->isSuccessful());

        // Verify invoice was approved and paid
        $invoice->refresh();
        $this->assertEquals(0, $invoice->is_draft);
        $this->assertNotNull($invoice->approved_at);
        $this->assertNotNull($invoice->approved_by);
        $this->assertEqualsDecimals(0, $invoice->invoice_due_amount);
    }

    /**
     * Test payment with address information
     */
    public function test_pay_invoice_with_address_information()
    {
        // Create draft invoice without address
        $invoice = Invoice::factory()->create([
            'payment_method_id' => PaymentMethodEnum::CREDIT_CARD,
            'payment_term_id' => PaymentTermFactory::new()->create()->id,
            'invoice_due_amount' => 300,
            'invoice_type_id' => InvoiceTypeEnum::INVOICE->value,
        ]);

        \Condoedge\Finance\Models\InvoiceDetail::factory()->create([
            'invoice_id' => $invoice->id,
            'quantity' => 1,
            'unit_price' => 300,
        ]);

        $invoice->refresh();

        // Configure mock gateway
        $this->mockGateway->reset();
        $this->mockGateway->setShouldSucceed(true);
        $this->mockGateway->setResponseData([
            'amount' => 300,
            'transactionId' => 'ADDRESS-001',
        ]);

        PaymentGatewayResolver::shouldReceive('resolve')
            ->once()
            ->andReturn($this->mockGateway);

        $this->mockRequestData($this->createTestPaymentRequest(300));

        // Include address in DTO
        $addressData = [
            'address1' => '123 Main St',
            'city' => 'Toronto',
            'state' => 'ON',
            'postal_code' => 'M5V 3A8',
            'country' => 'CA',
        ];

        $dto = new PayInvoiceDto([
            'invoice_id' => $invoice->id,
            'payment_method_id' => PaymentMethodEnum::CREDIT_CARD->value,
            'payment_term_id' => $invoice->payment_term_id,
            'address' => $addressData,
        ]);

        $result = InvoiceService::payInvoice($dto);

        $this->assertInstanceOf(PaymentResult::class, $result);
        $this->assertTrue($result->isSuccessful());

        // Verify invoice has address
        $invoice->refresh();
        $this->assertNotNull($invoice->address);
        $this->assertEquals('123 Main St', $invoice->address->address1);
        $this->assertEquals('Toronto', $invoice->address->city);
    }

    /**
     * Test that payment gateway receives correct context
     */
    public function test_payment_gateway_receives_correct_context()
    {
        $invoice = $this->createTestInvoice(600, PaymentMethodEnum::CREDIT_CARD);

        $this->mockGateway->reset();
        $this->mockGateway->setShouldSucceed(true);
        $this->mockGateway->setResponseData(['amount' => 600]);

        PaymentGatewayResolver::shouldReceive('resolve')
            ->once()
            ->withArgs(function (PaymentContext $context) use ($invoice) {
                // Capture and verify the context
                $this->assertInstanceOf(PaymentContext::class, $context);
                $this->assertEquals($invoice->id, $context->payable->getPayableId());
                $this->assertEquals(PaymentMethodEnum::CREDIT_CARD, $context->paymentMethod);
                $this->assertIsArray($context->paymentData);

                return true;
            })
            ->andReturn($this->mockGateway);

        $this->mockRequestData($this->createTestPaymentRequest(600));

        $dto = new PayInvoiceDto([
            'invoice_id' => $invoice->id,
            'payment_method_id' => PaymentMethodEnum::CREDIT_CARD->value,
            'payment_term_id' => $invoice->payment_term_id,
        ]);

        InvoiceService::payInvoice($dto);

        // Verify gateway was called with correct context
        $lastContext = $this->mockGateway->getLastContext();
        $this->assertNotEmpty($lastContext);
    }

    /**
     * Test pending payment (3DS, redirects, etc.)
     */
    public function test_pay_invoice_handles_pending_payment()
    {
        $invoice = $this->createTestInvoice(1000, PaymentMethodEnum::CREDIT_CARD);

        // Configure mock for pending payment (3DS)
        $this->mockGateway->reset();
        $this->mockGateway->setPending(true, 'https://bank.example.com/3ds-verify');
        $this->mockGateway->setResponseData([
            'transactionId' => 'PENDING-3DS-001',
        ]);

        PaymentGatewayResolver::shouldReceive('resolve')
            ->once()
            ->andReturn($this->mockGateway);

        $this->mockRequestData($this->createTestPaymentRequest(1000));

        $dto = new PayInvoiceDto([
            'invoice_id' => $invoice->id,
            'payment_method_id' => PaymentMethodEnum::CREDIT_CARD->value,
            'payment_term_id' => $invoice->payment_term_id,
        ]);

        $result = InvoiceService::payInvoice($dto);

        $this->assertInstanceOf(PaymentResult::class, $result);
        $this->assertTrue($result->isPending);
        $this->assertEquals('https://bank.example.com/3ds-verify', $result->redirectUrl);
        $this->assertEquals('PENDING-3DS-001', $result->transactionId);

        // Invoice should remain unpaid until payment is confirmed
        $invoice->refresh();
        $this->assertNotEquals(InvoiceStatusEnum::PAID->value, $invoice->invoice_status_id->value);
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
     * Create test payment request data
     * Override parent method to match new structure
     */
    protected function createTestPaymentRequest($amount = null, array $overrides = []): array
    {
        $defaultRequest = [
            'amount' => $amount ?? $this->faker->randomFloat(2, 10, 1000),
            'stripe_payment_method_id' => 'pm_card_visa_test',
            'complete_name' => $this->faker->name,
            'billing_address' => [
                'street' => $this->faker->streetAddress,
                'city' => $this->faker->city,
                'state' => $this->faker->stateAbbr,
                'postal_code' => $this->faker->postcode,
                'country' => 'US',
            ],
        ];

        return array_merge($defaultRequest, $overrides);
    }
}

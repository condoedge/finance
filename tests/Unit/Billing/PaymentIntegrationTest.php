<?php

namespace Condoedge\Finance\Tests\Unit\Billing;

use Condoedge\Finance\Billing\DefaultPaymentGatewayResolver;
use Condoedge\Finance\Billing\FinancialPayableInterface;
use Condoedge\Finance\Billing\PaymentContext;
use Condoedge\Finance\Billing\PaymentProcessor;
use Condoedge\Finance\Billing\PaymentProviderRegistry;
use Condoedge\Finance\Casts\SafeDecimal;
use Condoedge\Finance\Database\Factories\CustomerFactory;
use Condoedge\Finance\Database\Factories\GlAccountFactory;
use Condoedge\Finance\Database\Factories\InvoiceFactory;
use Condoedge\Finance\Database\Factories\PaymentTermFactory;
use Condoedge\Finance\Facades\InvoiceService;
use Condoedge\Finance\Facades\PaymentGatewayResolver;
use Condoedge\Finance\Facades\PaymentProcessor as PaymentProcessorFacade;
use Condoedge\Finance\Models\Customer;
use Condoedge\Finance\Models\CustomerPayment;
use Condoedge\Finance\Models\Dto\Invoices\CreateInvoiceDto;
use Condoedge\Finance\Models\HistoricalCustomer;
use Condoedge\Finance\Models\Invoice;
use Condoedge\Finance\Models\InvoiceTypeEnum;
use Condoedge\Finance\Models\PaymentMethodEnum;
use Condoedge\Finance\Models\PaymentTrace;
use Condoedge\Finance\Models\PaymentTraceStatusEnum;
use Tests\Mocks\MockPaymentGateway;
use Condoedge\Utils\Models\ContactInfo\Maps\Address;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Kompo\Auth\Database\Factories\UserFactory;
use Tests\Unit\Billing\PaymentTestCase;

/**
 * Integration test for the complete payment flow
 * Tests the interaction between all payment system components
 */
class PaymentIntegrationTest extends PaymentTestCase
{

    private PaymentProcessor $processor;
    private PaymentProviderRegistry $registry;
    private DefaultPaymentGatewayResolver $resolver;
    private MockPaymentGateway $mockGateway;

    public function setUp(): void
    {
        parent::setUp();

        /** @var \Kompo\Auth\Models\User $user */
        $user = UserFactory::new()->create()->first();
        if (!$user) {
            throw new Exception('Unknown error creating user');
        }
        $this->actingAs($user);

        // Set up the payment system components
        $this->mockGateway = new MockPaymentGateway();

        // Configure payment method to use mock gateway
        config(['kompo-finance.payment_method_providers' => [
            PaymentMethodEnum::CREDIT_CARD->value => MockPaymentGateway::class,
            PaymentMethodEnum::BANK_TRANSFER->value => MockPaymentGateway::class,
        ]]);

        // Register in service container
        $this->app->instance(MockPaymentGateway::class, $this->mockGateway);

        // Create and bind registry
        $this->registry = new PaymentProviderRegistry();
        $this->registry->register($this->mockGateway);
        $this->app->instance(PaymentProviderRegistry::class, $this->registry);

        // Create and bind resolver
        $this->resolver = new DefaultPaymentGatewayResolver($this->registry);
        $this->app->instance('PaymentGatewayResolver', $this->resolver);

        // Create processor
        $this->processor = new PaymentProcessor();
        $this->app->instance('PaymentProcessor', $this->processor);
    }

    public function test_complete_payment_flow_with_invoice()
    {
        // Create an invoice
        $customer = CustomerFactory::new()->create();
        $invoice = $this->createTestInvoice(250.00);

        // Make invoice implement FinancialPayableInterface
        $invoice = new class($invoice) extends Invoice implements FinancialPayableInterface {
            private Invoice $invoice;
            public array $events = [];

            public function __construct(Invoice $invoice)
            {
                $this->invoice = $invoice;
                $this->setRawAttributes($invoice->getAttributes(), true);
                $this->exists = true;
            }

            public function getPayableId(): int
            {
                return $this->invoice->id;
            }

            public function getPayableType(): string
            {
                return 'invoice';
            }

            public function getTeamId(): int
            {
                return $this->invoice->team_id ?? 1;
            }

            public function getPayableAmount(): SafeDecimal
            {
                return new SafeDecimal($this->invoice->invoice_due_amount);
            }

            public function getPayableLines(): Collection
            {
                return $this->invoice->invoiceDetails->map(function ($detail) {
                    return (object) [
                        'description' => $detail->name,
                        'amount' => $detail->total_amount,
                    ];
                });
            }

            public function getPaymentDescription(): string
            {
                return 'Payment for Invoice #' . $this->invoice->id;
            }

            public function getPaymentMetadata(): array
            {
                return [
                    'invoice_id' => $this->invoice->id,
                    'invoice_number' => $this->invoice->invoice_number,
                ];
            }

            public function getAddress(): ?Address
            {
                return null;
            }

            public function getEmail(): ?string
            {
                return $this->invoice->customer->email ?? 'customer@example.com';
            }

            public function getCustomerName(): ?string
            {
                return $this->invoice->customer->name ?? 'Test Customer';
            }

            public function getCustomer(): Customer|HistoricalCustomer
            {
                return $this->invoice->customer;
            }

            public function onPaymentSuccess(CustomerPayment $payment): void
            {
                $this->events[] = ['type' => 'success', 'payment' => $payment];
                // In real implementation, would apply payment to invoice
            }

            public function onPaymentFailed(array $errorData): void
            {
                $this->events[] = ['type' => 'failed', 'error' => $errorData];
            }
        };

        // Configure mock gateway for success
        $this->mockGateway->setShouldSucceed(true);

        // Create payment context
        $context = new PaymentContext(
            payable: $invoice,
            paymentMethod: PaymentMethodEnum::CREDIT_CARD,
            paymentData: [
                'payment_method_id' => 'pm_card_visa',
                'complete_name' => 'John Doe',
            ],
            metadata: ['test_run' => true]
        );

        // Process payment
        $result = PaymentProcessorFacade::processPayment($context);

        // Assertions
        $this->assertTrue($result->isSuccessful());
        $this->assertEquals(250.00, $result->amount);

        // Check payment trace
        $trace = PaymentTrace::where('payable_id', $invoice->id)
            ->where('payable_type', 'invoice')
            ->first();

        $this->assertNotNull($trace);
        $this->assertEquals(PaymentTraceStatusEnum::COMPLETED, $trace->status);
        $this->assertEquals('mock_gateway', $trace->payment_provider_code);
        $this->assertEquals(PaymentMethodEnum::CREDIT_CARD, $trace->payment_method_id);

        // Check customer payment
        $payment = CustomerPayment::where('customer_id', $customer->id)->first();
        $this->assertNotNull($payment);
        $this->assertEquals(250.00, $payment->amount->toFloat());
        $this->assertEquals($trace->id, $payment->payment_trace_id);

        // Check callbacks were called
        $this->assertCount(1, $invoice->events);
        $this->assertEquals('success', $invoice->events[0]['type']);
        $this->assertEquals($payment->id, $invoice->events[0]['payment']->id);
    }

    public function test_payment_flow_with_3ds_authentication()
    {
        // Create payable
        $customer = CustomerFactory::new()->create();
        $invoice = $this->createTestInvoice(500.00);

        // Configure mock for 3DS
        $this->mockGateway->simulateScenario('3ds_required');

        $context = new PaymentContext(
            payable: $this->wrapInvoiceAsPayable($invoice),
            paymentMethod: PaymentMethodEnum::CREDIT_CARD,
            paymentData: [
                'payment_method_id' => 'pm_card_3ds',
                'complete_name' => 'Jane Doe',
            ],
            returnUrl: 'https://example.com/payment/return',
            cancelUrl: 'https://example.com/payment/cancel'
        );

        // Process payment
        $result = $this->processor->processPayment($context);

        // Assertions for pending payment
        $this->assertFalse($result->isSuccessful());
        $this->assertTrue($result->isPending);
        $this->assertNotNull($result->redirectUrl);
        $this->assertEquals('https://mock-gateway.test/3ds-verify', $result->redirectUrl);

        // Check payment trace is in processing state
        $trace = PaymentTrace::where('external_transaction_ref', $result->transactionId)->first();
        $this->assertNotNull($trace);
        $this->assertEquals(PaymentTraceStatusEnum::PROCESSING, $trace->status);

        // No customer payment should be created yet
        $payment = CustomerPayment::where('customer_id', $customer->id)->first();
        $this->assertNull($payment);
    }

    public function test_payment_flow_handles_network_failures()
    {
        // Create payable
        $customer = CustomerFactory::new()->create();
        $invoice = $this->createTestInvoice( 100.00);

        // Configure mock for network timeout
        $this->mockGateway->simulateScenario('network_timeout');

        $context = new PaymentContext(
            payable: $this->wrapInvoiceAsPayable($invoice),
            paymentMethod: PaymentMethodEnum::CREDIT_CARD,
            paymentData: ['payment_method_id' => 'pm_test', 'complete_name' => 'Test User']
        );

        // Count before attempt
        $traceCountBefore = PaymentTrace::count();
        $paymentCountBefore = CustomerPayment::count();

        // Process payment
        $result = $this->processor->processPayment($context);

        // Assertions
        $this->assertFalse($result->isSuccessful());
        $this->assertEquals('Network timeout after 30 seconds', $result->errorMessage);

        // Check payment trace shows failure
        $trace = PaymentTrace::where('external_transaction_ref', $result->transactionId)->first();
        $this->assertNotNull($trace);
        $this->assertEquals(PaymentTraceStatusEnum::FAILED, $trace->status);

        // No customer payment created
        $this->assertEquals($paymentCountBefore, CustomerPayment::count());
    }

    public function test_concurrent_payment_processing()
    {
        // Create multiple invoices
        $customer = CustomerFactory::new()->create();
        $invoices = [];

        for ($i = 1; $i <= 5; $i++) {
            $invoices[] = $this->createTestInvoice($i * 100);
        }

        $this->mockGateway->setShouldSucceed(true);

        // Process all payments
        $results = [];
        foreach ($invoices as $invoice) {
            $context = new PaymentContext(
                payable: $this->wrapInvoiceAsPayable($invoice),
                paymentMethod: PaymentMethodEnum::CREDIT_CARD,
                paymentData: ['payment_method_id' => 'pm_test', 'complete_name' => 'Test User']
            );

            $results[] = $this->processor->processPayment($context);
        }

        // Verify all succeeded
        $this->assertCount(5, $results);
        foreach ($results as $result) {
            $this->assertTrue($result->isSuccessful());
        }

        // Check all traces created
        $traces = PaymentTrace::where('payment_provider_code', 'mock_gateway')->get();
        $this->assertCount(5, $traces);

        // Check all payments created
        $payments = CustomerPayment::where('customer_id', $customer->id)->get();
        $this->assertCount(5, $payments);

        // Verify total amount
        $totalAmount = $payments->sum('amount');
        $this->assertEquals(1500.00, $totalAmount); // 100 + 200 + 300 + 400 + 500
    }

    public function test_payment_flow_with_different_payment_methods()
    {
        // Test Credit Card
        $ccInvoice = $this->createTestInvoice(200.00);
        $ccContext = new PaymentContext(
            payable: $this->wrapInvoiceAsPayable($ccInvoice),
            paymentMethod: PaymentMethodEnum::CREDIT_CARD,
            paymentData: ['payment_method_id' => 'pm_card', 'complete_name' => 'CC User']
        );

        // Test Bank Transfer
        $bankInvoice = $this->createTestInvoice(300.00);
        $bankContext = new PaymentContext(
            payable: $this->wrapInvoiceAsPayable($bankInvoice),
            paymentMethod: PaymentMethodEnum::BANK_TRANSFER,
            paymentData: [
                'account_holder_name' => 'Bank User',
                'transit_number' => '12345',
                'institution_number' => '123',
                'account_number' => '1234567',
                'authorize_debit' => true,
            ]
        );

        $this->mockGateway->setShouldSucceed(true);

        // Process both payments
        $ccResult = $this->processor->processPayment($ccContext);
        $bankResult = $this->processor->processPayment($bankContext);

        // Verify both succeeded
        $this->assertTrue($ccResult->isSuccessful());
        $this->assertTrue($bankResult->isSuccessful());

        // Check traces have correct payment methods
        $ccTrace = PaymentTrace::where('external_transaction_ref', $ccResult->transactionId)->first();
        $bankTrace = PaymentTrace::where('external_transaction_ref', $bankResult->transactionId)->first();

        $this->assertEquals(PaymentMethodEnum::CREDIT_CARD, $ccTrace->payment_method_id);
        $this->assertEquals(PaymentMethodEnum::BANK_TRANSFER, $bankTrace->payment_method_id);
    }

    public function test_payment_rollback_on_post_processing_failure()
    {
        // Create a special payable that throws exception in success callback
        $customer = CustomerFactory::new()->create();
        $invoice = $this->createTestInvoice(150.00);

        $failingPayable = new class($invoice, $customer) implements FinancialPayableInterface {
            public function __construct(
                private Invoice $invoice,
                private Customer $customer
            ) {}

            public function getPayableId(): int
            {
                return $this->invoice->id;
            }
            public function getPayableType(): string
            {
                return 'failing_invoice';
            }
            public function getTeamId(): int
            {
                return 1;
            }
            public function getPayableAmount(): SafeDecimal
            {
                return new SafeDecimal(150);
            }
            public function getPayableLines(): Collection
            {
                return collect();
            }
            public function getPaymentDescription(): string
            {
                return 'Test';
            }
            public function getPaymentMetadata(): array
            {
                return [];
            }
            public function getAddress(): ?Address
            {
                return null;
            }
            public function getEmail(): ?string
            {
                return 'test@example.com';
            }
            public function getCustomerName(): ?string
            {
                return 'Test';
            }
            public function getCustomer(): Customer|HistoricalCustomer
            {
                return $this->customer;
            }

            public function onPaymentSuccess(CustomerPayment $payment): void
            {
                throw new \RuntimeException('Simulated post-processing failure');
            }

            public function onPaymentFailed(array $errorData): void {}
        };

        $this->mockGateway->setShouldSucceed(true);

        $context = new PaymentContext(
            payable: $failingPayable,
            paymentMethod: PaymentMethodEnum::CREDIT_CARD,
            paymentData: ['payment_method_id' => 'pm_test', 'complete_name' => 'Test']
        );

        // Record counts before
        $traceCountBefore = PaymentTrace::count();
        $paymentCountBefore = CustomerPayment::count();

        // Process payment - should throw exception
        try {
            $this->processor->processPayment($context);
            $this->fail('Expected exception was not thrown');
        } catch (\Exception $e) {
            $this->assertStringContainsString('Payment processing failed', $e->getMessage());
        }

        // Verify complete rollback
        $this->assertEquals($traceCountBefore, PaymentTrace::count());
        $this->assertEquals($paymentCountBefore, CustomerPayment::count());
    }

    // Helper methods

    private function wrapInvoiceAsPayable(Invoice $invoice): FinancialPayableInterface
    {
        return new class($invoice) implements FinancialPayableInterface {
            public function __construct(private Invoice $invoice) {}

            public function getPayableId(): int
            {
                return $this->invoice->id;
            }
            public function getPayableType(): string
            {
                return 'invoice';
            }
            public function getTeamId(): int
            {
                return $this->invoice->team_id ?? 1;
            }
            public function getPayableAmount(): SafeDecimal
            {
                return new SafeDecimal($this->invoice->invoice_due_amount);
            }
            public function getPayableLines(): Collection
            {
                return collect();
            }
            public function getPaymentDescription(): string
            {
                return 'Invoice #' . $this->invoice->id;
            }
            public function getPaymentMetadata(): array
            {
                return ['invoice_id' => $this->invoice->id];
            }
            public function getAddress(): ?Address
            {
                return null;
            }
            public function getEmail(): ?string
            {
                return 'test@example.com';
            }
            public function getCustomerName(): ?string
            {
                return 'Test Customer';
            }
            public function getCustomer(): Customer|HistoricalCustomer
            {
                return $this->invoice->customer;
            }
            public function onPaymentSuccess(CustomerPayment $payment): void {}
            public function onPaymentFailed(array $errorData): void {}
        };
    }
}

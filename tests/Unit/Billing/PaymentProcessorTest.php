<?php

namespace Condoedge\Finance\Tests\Unit\Billing;

use Condoedge\Finance\Billing\Contracts\FinancialPayableInterface;
use Condoedge\Finance\Billing\Contracts\PayableInterface;
use Condoedge\Finance\Billing\Contracts\PaymentGatewayResolverInterface;
use Condoedge\Finance\Billing\Core\PaymentContext;
use Condoedge\Finance\Billing\Core\PaymentProcessor;
use Condoedge\Finance\Billing\Core\PaymentProviderRegistry;
use Condoedge\Finance\Billing\Core\PaymentResult;
use Condoedge\Finance\Billing\Exceptions\PaymentProcessingException;
use Condoedge\Finance\Casts\SafeDecimal;
use Condoedge\Finance\Database\Factories\CustomerFactory;
use Condoedge\Finance\Models\Customer;
use Condoedge\Finance\Models\CustomerPayment;
use Condoedge\Finance\Models\PaymentMethodEnum;
use Condoedge\Finance\Models\PaymentTrace;
use Condoedge\Finance\Models\PaymentTraceStatusEnum;
use Condoedge\Utils\Models\ContactInfo\Maps\Address;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\Mocks\MockPaymentGateway;
use Tests\Unit\Billing\PaymentTestCase;

/**
 * Mock Payable for testing
 */
class MockPayable implements PayableInterface, FinancialPayableInterface
{
    public function __construct(
        private int $id,
        private string $type,
        private int $teamId,
        private float $amount,
        private Customer $customer,
        private array $metadata = []
    ) {
    }

    public function getPayableId(): int
    {
        return $this->id;
    }

    public function getPayableType(): string
    {
        return $this->type;
    }

    public function getTeamId(): int
    {
        return $this->teamId;
    }

    public function getPayableAmount(): SafeDecimal
    {
        return new SafeDecimal($this->amount);
    }

    public function getPayableLines(): Collection
    {
        return collect([
            (object) ['description' => 'Test Line 1', 'amount' => $this->amount * 0.6],
            (object) ['description' => 'Test Line 2', 'amount' => $this->amount * 0.4],
        ]);
    }

    public function getPaymentDescription(): string
    {
        return 'Test payment for ' . $this->type . ' #' . $this->id;
    }

    public function getPaymentMetadata(): array
    {
        return array_merge(['test' => true], $this->metadata);
    }

    public function getAddress(): ?Address
    {
        return new Address([
            'street_number' => '123',
            'address1' => 'Test Street',
            'city' => 'Test City',
            'postal_code' => 'T1T 1T1',
            'country' => 'CA',
        ]);
    }

    public function getEmail(): ?string
    {
        return 'test@example.com';
    }

    public function getCustomerName(): ?string
    {
        return 'Test Customer';
    }

    // FinancialPayableInterface methods
    public function getCustomer(): Customer
    {
        return $this->customer;
    }

    public function onPaymentSuccess(CustomerPayment $payment): void
    {
        // Track that payment was successful
        $this->metadata['payment_success'] = true;
        $this->metadata['payment_id'] = $payment->id;
    }

    public function onPaymentFailed(array $errorData): void
    {
        // Track that payment failed
        $this->metadata['payment_failed'] = true;
        $this->metadata['error_data'] = $errorData;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }
}

class PaymentProcessorTest extends PaymentTestCase
{
    private PaymentProcessor $processor;
    private MockPaymentGateway $mockGateway;
    private PaymentProviderRegistry $registry;
    private PaymentGatewayResolverInterface $resolver;

    public function setUp(): void
    {
        parent::setUp();

        // Create mock gateway
        $this->mockGateway = new MockPaymentGateway();

        // Create registry and register mock gateway
        $this->registry = new PaymentProviderRegistry();
        $this->registry->register($this->mockGateway);

        // Create mock resolver
        $this->resolver = Mockery::mock(PaymentGatewayResolverInterface::class);
        $this->resolver->shouldReceive('resolve')
            ->andReturn($this->mockGateway)
            ->byDefault();

        // Bind mock resolver to container
        $this->app->instance(PaymentGatewayResolverInterface::class, $this->resolver);
        $this->app->instance('PaymentGatewayResolver', $this->resolver);

        // Create processor
        $this->processor = new PaymentProcessor();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_processes_successful_payment()
    {
        // Arrange
        $customer = CustomerFactory::new()->create();
        $payable = new MockPayable(1, 'test_order', 1, 100.00, $customer);
        $context = new PaymentContext(
            payable: $payable,
            paymentMethod: PaymentMethodEnum::CREDIT_CARD,
            paymentData: ['card_number' => '4111111111111111'],
            metadata: ['order_ref' => 'ORD-123']
        );

        $this->mockGateway->setShouldSucceed(true);

        // Act
        $result = $this->processor->processPayment($context);

        // Assert
        $this->assertInstanceOf(PaymentResult::class, $result);
        $this->assertTrue($result->isSuccessful());
        $this->assertEquals(100.00, $result->amount);
        $this->assertEquals('mock_gateway', $result->paymentProviderCode);

        // Check payment trace was created
        $trace = PaymentTrace::where('external_transaction_ref', $result->transactionId)->first();
        $this->assertNotNull($trace);
        $this->assertEquals(PaymentTraceStatusEnum::COMPLETED, $trace->status);
        $this->assertEquals($payable->getPayableId(), $trace->payable_id);
        $this->assertEquals($payable->getPayableType(), $trace->payable_type);

        // Check customer payment was created
        $payment = CustomerPayment::where('payment_trace_id', $trace->id)->first();
        $this->assertNotNull($payment);
        $this->assertEquals(100.00, $payment->amount->toFloat());
        $this->assertEquals($customer->id, $payment->customer_id);

        // Check payable callbacks were called
        $this->assertTrue($payable->getMetadata()['payment_success'] ?? false);
        $this->assertEquals($payment->id, $payable->getMetadata()['payment_id'] ?? null);
    }

    public function test_it_handles_failed_payment()
    {
        // Arrange
        $customer = CustomerFactory::new()->create();
        $payable = new MockPayable(2, 'test_order', 1, 200.00, $customer);
        $context = new PaymentContext(
            payable: $payable,
            paymentMethod: PaymentMethodEnum::CREDIT_CARD
        );

        $this->mockGateway->setShouldSucceed(false)
            ->setErrorMessage('Card declined');

        // Act
        $result = $this->processor->processPayment($context);

        // Assert
        $this->assertInstanceOf(PaymentResult::class, $result);
        $this->assertFalse($result->isSuccessful());
        $this->assertEquals('Card declined', $result->errorMessage);

        // Check payment trace was created with failed status
        $trace = PaymentTrace::where('external_transaction_ref', $result->transactionId)->first();
        $this->assertNotNull($trace);
        $this->assertEquals(PaymentTraceStatusEnum::FAILED, $trace->status);

        // Check no customer payment was created
        $payment = CustomerPayment::where('payment_trace_id', $trace->id)->first();
        $this->assertNull($payment);

        // Check payable failure callback was called
        $this->assertTrue($payable->getMetadata()['payment_failed'] ?? false);
        $errorData = $payable->getMetadata()['error_data'] ?? [];
        $this->assertEquals('Card declined', $errorData['error']);
    }

    public function test_it_handles_pending_payment()
    {
        // Arrange
        $customer = CustomerFactory::new()->create();
        $payable = new MockPayable(3, 'test_order', 1, 300.00, $customer);
        $context = new PaymentContext(
            payable: $payable,
            paymentMethod: PaymentMethodEnum::CREDIT_CARD,
            returnUrl: 'https://example.com/return'
        );

        $this->mockGateway->setPending(true, 'https://gateway.test/3ds');

        // Act
        $result = $this->processor->processPayment($context);

        // Assert
        $this->assertInstanceOf(PaymentResult::class, $result);
        $this->assertFalse($result->isSuccessful());
        $this->assertTrue($result->isPending);
        $this->assertEquals('https://gateway.test/3ds', $result->redirectUrl);

        // Check payment trace was created with processing status
        $trace = PaymentTrace::where('external_transaction_ref', $result->transactionId)->first();
        $this->assertNotNull($trace);
        $this->assertEquals(PaymentTraceStatusEnum::PROCESSING, $trace->status);

        // Check no customer payment was created yet
        $payment = CustomerPayment::where('payment_trace_id', $trace->id)->first();
        $this->assertNull($payment);

        // Check no callbacks were called for pending
        $this->assertFalse($payable->getMetadata()['payment_success'] ?? false);
        $this->assertFalse($payable->getMetadata()['payment_failed'] ?? false);
    }

    public function test_it_rolls_back_transaction_on_exception()
    {
        // Arrange
        $customer = CustomerFactory::new()->create();
        $payable = new MockPayable(4, 'test_order', 1, 400.00, $customer);
        $context = new PaymentContext(
            payable: $payable,
            paymentMethod: PaymentMethodEnum::CREDIT_CARD
        );

        // Mock gateway to throw exception
        $this->resolver->shouldReceive('resolve')
            ->andThrow(new Exception('Gateway connection failed'));

        $traceCountBefore = PaymentTrace::count();
        $paymentCountBefore = CustomerPayment::count();

        // Act & Assert
        try {
            $this->processor->processPayment($context);
            $this->fail('Expected PaymentProcessingException was not thrown');
        } catch (PaymentProcessingException $e) {
            $this->assertEquals('Payment processing failed', $e->getMessage());
            $this->assertEquals('Gateway connection failed', $e->getPrevious()->getMessage());
        }

        // Verify rollback
        $this->assertEquals($traceCountBefore, PaymentTrace::count());
        $this->assertEquals($paymentCountBefore, CustomerPayment::count());
    }

    public function test_it_handles_non_financial_payable()
    {
        // Create a payable that doesn't implement FinancialPayableInterface
        $payable = Mockery::mock(PayableInterface::class);
        $payable->shouldReceive('getPayableId')->andReturn(5);
        $payable->shouldReceive('getPayableType')->andReturn('non_financial');
        $payable->shouldReceive('getTeamId')->andReturn(1);
        $payable->shouldReceive('getPayableAmount')->andReturn(new SafeDecimal(100));

        $context = new PaymentContext(
            payable: $payable,
            paymentMethod: PaymentMethodEnum::CREDIT_CARD
        );

        $this->mockGateway->setShouldSucceed(true);

        // Act & Assert
        try {
            $this->processor->processPayment($context);
            $this->fail('Expected exception was not thrown');
        } catch (PaymentProcessingException $e) {
            $this->assertStringContainsString('Payment processing failed', $e->getMessage());
            $this->assertStringContainsString('Unsupported payable type', $e->getPrevious()->getMessage());
        }
    }

    public function test_it_updates_existing_payment_trace()
    {
        // Arrange
        $customer = CustomerFactory::new()->create();
        $transactionId = 'TXN-EXISTING-123';

        // Create existing trace
        $existingTrace = PaymentTrace::createOrUpdateTrace(
            $transactionId,
            PaymentTraceStatusEnum::PROCESSING,
            100,
            'test_order',
            'mock_gateway',
            PaymentMethodEnum::CREDIT_CARD->value
        );

        $payable = new MockPayable(100, 'test_order', 1, 500.00, $customer);
        $context = new PaymentContext(
            payable: $payable,
            paymentMethod: PaymentMethodEnum::CREDIT_CARD
        );

        $this->mockGateway->setShouldSucceed(true)
            ->setResponseData(['transactionId' => $transactionId]);

        // Act
        $result = $this->processor->processPayment($context);

        // Assert
        $this->assertTrue($result->isSuccessful());

        // Check trace was updated, not duplicated
        $traceCount = PaymentTrace::where('external_transaction_ref', $transactionId)->count();
        $this->assertEquals(1, $traceCount);

        $updatedTrace = PaymentTrace::find($existingTrace->id);
        $this->assertEquals(PaymentTraceStatusEnum::COMPLETED, $updatedTrace->status);
    }

    public function test_it_includes_metadata_in_payment_result()
    {
        // Arrange
        $customer = CustomerFactory::new()->create();
        $payable = new MockPayable(6, 'test_order', 1, 150.00, $customer, ['custom_field' => 'custom_value']);
        $context = new PaymentContext(
            payable: $payable,
            paymentMethod: PaymentMethodEnum::CREDIT_CARD,
            metadata: ['request_id' => 'REQ-123']
        );

        $this->mockGateway->setShouldSucceed(true)
            ->setResponseData([
                'metadata' => ['gateway_ref' => 'GATEWAY-456']
            ]);

        // Act
        $result = $this->processor->processPayment($context);

        // Assert
        $this->assertTrue($result->isSuccessful());
        $this->assertArrayHasKey('test', $result->metadata);
        $this->assertArrayHasKey('request_id', $result->metadata);
        $this->assertArrayHasKey('gateway_ref', $result->metadata);
    }

    public function test_it_gets_payment_form_from_gateway()
    {
        // Arrange
        $customer = CustomerFactory::new()->create();
        $payable = new MockPayable(7, 'test_order', 1, 100.00, $customer);
        $context = new PaymentContext(
            payable: $payable,
            paymentMethod: PaymentMethodEnum::CREDIT_CARD
        );

        // Act
        $form = $this->processor->getPaymentForm($context);

        // Assert
        $this->assertNotNull($form);
        $this->assertInstanceOf(\Kompo\Elements\BaseElement::class, $form);
    }

    public function test_it_logs_payment_failures()
    {
        // Arrange
        Log::shouldReceive('error')
            ->twice() // Once for payment failed, once for processing failed
            ->with(Mockery::type('string'), Mockery::type('array'));

        $customer = CustomerFactory::new()->create();
        $payable = new MockPayable(8, 'test_order', 1, 100.00, $customer);
        $context = new PaymentContext(
            payable: $payable,
            paymentMethod: PaymentMethodEnum::CREDIT_CARD
        );

        $this->mockGateway->setShouldSucceed(false)
            ->setErrorMessage('Test error for logging');

        // Act
        $result = $this->processor->processPayment($context);

        // Assert
        $this->assertFalse($result->isSuccessful());
    }

    public function test_it_handles_multiple_concurrent_payments()
    {
        // Arrange
        $customer = CustomerFactory::new()->create();
        $contexts = [];

        for ($i = 1; $i <= 3; $i++) {
            $payable = new MockPayable($i, 'test_order', 1, $i * 100, $customer);
            $contexts[] = new PaymentContext(
                payable: $payable,
                paymentMethod: PaymentMethodEnum::CREDIT_CARD
            );
        }

        $this->mockGateway->setShouldSucceed(true);

        // Act - Process all payments
        $results = [];
        foreach ($contexts as $context) {
            $results[] = $this->processor->processPayment($context);
        }

        // Assert
        $this->assertCount(3, $results);
        foreach ($results as $index => $result) {
            $this->assertTrue($result->isSuccessful());
            $this->assertEquals(($index + 1) * 100, $result->amount);
        }

        // Check all payments were created
        $payments = CustomerPayment::where('customer_id', $customer->id)->get();
        $this->assertCount(3, $payments);
        $this->assertEquals(600, $payments->sum('amount'));
    }
}

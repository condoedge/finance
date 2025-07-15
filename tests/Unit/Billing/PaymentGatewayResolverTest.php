<?php

namespace Condoedge\Finance\Tests\Unit\Billing;

use Condoedge\Finance\Billing\Contracts\PayableInterface;
use Condoedge\Finance\Billing\Contracts\PaymentGatewayInterface;
use Condoedge\Finance\Billing\Core\PaymentContext;
use Condoedge\Finance\Billing\Core\PaymentProviderRegistry;
use Condoedge\Finance\Billing\Core\Resolver\DefaultPaymentGatewayResolver;
use Condoedge\Finance\Billing\Providers\Stripe\StripePaymentProvider;
use Condoedge\Finance\Casts\SafeDecimal;
use Condoedge\Finance\Models\PaymentMethodEnum;
use Condoedge\Finance\Tests\Mocks\MockPaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class PaymentGatewayResolverTest extends TestCase
{
    use RefreshDatabase;

    private DefaultPaymentGatewayResolver $resolver;
    private PaymentProviderRegistry $registry;
    private MockPaymentGateway $mockGateway;
    private PaymentGatewayInterface $stripeGateway;

    public function setUp(): void
    {
        parent::setUp();

        // Create gateways
        $this->mockGateway = new MockPaymentGateway();
        $this->stripeGateway = Mockery::mock(StripePaymentProvider::class);
        $this->stripeGateway->shouldReceive('getCode')->andReturn('stripe');
        $this->stripeGateway->shouldReceive('getSupportedPaymentMethods')
            ->andReturn([PaymentMethodEnum::CREDIT_CARD, PaymentMethodEnum::BANK_TRANSFER]);

        // Create registry and register gateways
        $this->registry = new PaymentProviderRegistry();
        $this->registry->register($this->mockGateway);
        $this->registry->register($this->stripeGateway);

        // Create resolver
        $this->resolver = new DefaultPaymentGatewayResolver($this->registry);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_resolves_correct_gateway_for_payment_method()
    {
        // Create mock payable
        $payable = $this->createMockPayable();

        // Mock the payment method to return our mock gateway
        $originalGateway = PaymentMethodEnum::CREDIT_CARD->getDefaultPaymentGateway();

        // We need to mock the config to return our mock gateway class
        config(['kompo-finance.payment_method_providers' => [
            PaymentMethodEnum::CREDIT_CARD->value => MockPaymentGateway::class,
        ]]);

        $context = new PaymentContext(
            payable: $payable,
            paymentMethod: PaymentMethodEnum::CREDIT_CARD
        );

        // Act
        $gateway = $this->resolver->resolve($context);

        // Assert
        $this->assertInstanceOf(PaymentGatewayInterface::class, $gateway);
        $this->assertEquals('mock_gateway', $gateway->getCode());

        // Restore original config
        config(['kompo-finance.payment_method_providers' => [
            PaymentMethodEnum::CREDIT_CARD->value => $originalGateway,
        ]]);
    }

    public function test_it_returns_available_gateways_for_payment_method()
    {
        // Arrange
        $payable = $this->createMockPayable();
        $context = new PaymentContext(
            payable: $payable,
            paymentMethod: PaymentMethodEnum::CREDIT_CARD
        );

        // Act
        $availableGateways = $this->resolver->getAvailableGateways($context);

        // Assert
        $this->assertIsArray($availableGateways);
        $this->assertCount(2, $availableGateways); // mock and stripe both support credit card

        $codes = array_map(fn ($gateway) => $gateway->getCode(), $availableGateways);
        $this->assertContains('mock_gateway', $codes);
        $this->assertContains('stripe', $codes);
    }

    public function test_it_returns_empty_array_for_unsupported_payment_method()
    {
        // Arrange
        $payable = $this->createMockPayable();

        // Use a payment method not supported by any gateway
        $context = new PaymentContext(
            payable: $payable,
            paymentMethod: PaymentMethodEnum::CASH
        );

        // Act
        $availableGateways = $this->resolver->getAvailableGateways($context);

        // Assert
        $this->assertIsArray($availableGateways);
        $this->assertEmpty($availableGateways);
    }

    public function test_it_throws_exception_when_no_default_gateway_configured()
    {
        // Arrange
        $payable = $this->createMockPayable();

        // Clear the config for this payment method
        config(['kompo-finance.payment_method_providers' => [
            PaymentMethodEnum::INTERAC->value => null,
        ]]);

        $context = new PaymentContext(
            payable: $payable,
            paymentMethod: PaymentMethodEnum::INTERAC
        );

        // Act & Assert
        $this->expectException(\Exception::class);
        $this->resolver->resolve($context);
    }

    public function test_it_resolves_different_gateways_for_different_payment_methods()
    {
        // Arrange
        $payable = $this->createMockPayable();

        // Configure different gateways for different payment methods
        config(['kompo-finance.payment_method_providers' => [
            PaymentMethodEnum::CREDIT_CARD->value => MockPaymentGateway::class,
            PaymentMethodEnum::BANK_TRANSFER->value => StripePaymentProvider::class,
        ]]);

        // Act
        $creditCardContext = new PaymentContext(
            payable: $payable,
            paymentMethod: PaymentMethodEnum::CREDIT_CARD
        );
        $bankTransferContext = new PaymentContext(
            payable: $payable,
            paymentMethod: PaymentMethodEnum::BANK_TRANSFER
        );

        $creditCardGateway = $this->resolver->resolve($creditCardContext);
        $bankTransferGateway = $this->resolver->resolve($bankTransferContext);

        // Assert
        $this->assertEquals('mock_gateway', $creditCardGateway->getCode());
        $this->assertEquals('stripe', $bankTransferGateway->getCode());
        $this->assertNotSame($creditCardGateway, $bankTransferGateway);
    }

    public function test_it_filters_available_gateways_correctly()
    {
        // Arrange
        // Add a gateway that only supports bank transfers
        $bankOnlyGateway = Mockery::mock(PaymentGatewayInterface::class);
        $bankOnlyGateway->shouldReceive('getCode')->andReturn('bank_only');
        $bankOnlyGateway->shouldReceive('getSupportedPaymentMethods')
            ->andReturn([PaymentMethodEnum::BANK_TRANSFER]);

        $this->registry->register($bankOnlyGateway);

        $payable = $this->createMockPayable();

        // Act - Check available gateways for credit card
        $creditCardContext = new PaymentContext(
            payable: $payable,
            paymentMethod: PaymentMethodEnum::CREDIT_CARD
        );
        $creditCardGateways = $this->resolver->getAvailableGateways($creditCardContext);

        // Act - Check available gateways for bank transfer
        $bankTransferContext = new PaymentContext(
            payable: $payable,
            paymentMethod: PaymentMethodEnum::BANK_TRANSFER
        );
        $bankTransferGateways = $this->resolver->getAvailableGateways($bankTransferContext);

        // Assert
        $creditCardCodes = array_map(fn ($g) => $g->getCode(), $creditCardGateways);
        $bankTransferCodes = array_map(fn ($g) => $g->getCode(), $bankTransferGateways);

        $this->assertContains('mock_gateway', $creditCardCodes);
        $this->assertContains('stripe', $creditCardCodes);
        $this->assertNotContains('bank_only', $creditCardCodes);

        $this->assertContains('mock_gateway', $bankTransferCodes);
        $this->assertContains('stripe', $bankTransferCodes);
        $this->assertContains('bank_only', $bankTransferCodes);
    }

    public function test_it_maintains_singleton_instances_from_registry()
    {
        // Arrange
        config(['kompo-finance.payment_method_providers' => [
            PaymentMethodEnum::CREDIT_CARD->value => MockPaymentGateway::class,
        ]]);

        $payable = $this->createMockPayable();
        $context = new PaymentContext(
            payable: $payable,
            paymentMethod: PaymentMethodEnum::CREDIT_CARD
        );

        // Act - Resolve multiple times
        $gateway1 = $this->resolver->resolve($context);
        $gateway2 = $this->resolver->resolve($context);

        // Assert - Should be the same instance from registry
        $this->assertSame($gateway1, $gateway2);
    }

    // Helper methods

    private function createMockPayable(): PayableInterface
    {
        $payable = Mockery::mock(PayableInterface::class);
        $payable->shouldReceive('getPayableId')->andReturn(1);
        $payable->shouldReceive('getPayableType')->andReturn('test_order');
        $payable->shouldReceive('getTeamId')->andReturn(1);
        $payable->shouldReceive('getPayableAmount')->andReturn(new SafeDecimal(100));
        $payable->shouldReceive('getPayableLines')->andReturn(collect());
        $payable->shouldReceive('getPaymentDescription')->andReturn('Test payment');
        $payable->shouldReceive('getPaymentMetadata')->andReturn([]);
        $payable->shouldReceive('getAddress')->andReturn(null);
        $payable->shouldReceive('getEmail')->andReturn('test@example.com');
        $payable->shouldReceive('getCustomerName')->andReturn('Test Customer');

        return $payable;
    }
}

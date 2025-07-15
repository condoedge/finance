<?php

namespace Condoedge\Finance\Tests\Unit\Billing;

use Condoedge\Finance\Billing\Contracts\PaymentGatewayInterface;
use Condoedge\Finance\Billing\Core\PaymentProviderRegistry;
use Mockery;
use Tests\Mocks\MockPaymentGateway;
use Tests\TestCase;

class PaymentProviderRegistryTest extends TestCase
{
    private PaymentProviderRegistry $registry;

    public function setUp(): void
    {
        parent::setUp();

        // Clear any config providers for isolated testing
        config(['kompo-finance.payment_providers' => []]);

        $this->registry = new PaymentProviderRegistry();
    }

    public function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_registers_and_retrieves_providers()
    {
        // Arrange
        $mockProvider = new MockPaymentGateway();

        // Act
        $this->registry->register($mockProvider);
        $retrieved = $this->registry->get('mock_gateway');

        // Assert
        $this->assertSame($mockProvider, $retrieved);
        $this->assertEquals('mock_gateway', $retrieved->getCode());
    }

    public function test_it_throws_exception_for_unknown_provider()
    {
        // Act & Assert
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Payment provider 'unknown_provider' not found");

        $this->registry->get('unknown_provider');
    }

    public function test_it_returns_all_registered_providers()
    {
        // Arrange
        $provider1 = new MockPaymentGateway();

        $provider2 = Mockery::mock(PaymentGatewayInterface::class);
        $provider2->shouldReceive('getCode')->andReturn('provider2');

        $provider3 = Mockery::mock(PaymentGatewayInterface::class);
        $provider3->shouldReceive('getCode')->andReturn('provider3');

        // Act
        $this->registry->register($provider1);
        $this->registry->register($provider2);
        $this->registry->register($provider3);

        $allProviders = $this->registry->all();

        // Assert
        $this->assertCount(3, $allProviders);
        $this->assertArrayHasKey('mock_gateway', $allProviders);
        $this->assertArrayHasKey('provider2', $allProviders);
        $this->assertArrayHasKey('provider3', $allProviders);
        $this->assertSame($provider1, $allProviders['mock_gateway']);
        $this->assertSame($provider2, $allProviders['provider2']);
        $this->assertSame($provider3, $allProviders['provider3']);
    }

    public function test_it_loads_providers_from_config()
    {
        // Arrange
        config(['kompo-finance.payment_providers' => [
            MockPaymentGateway::class,
        ]]);

        // Act - Create new registry which should load from config
        $registry = new PaymentProviderRegistry();
        $allProviders = $registry->all();

        // Assert
        $this->assertCount(1, $allProviders);
        $this->assertArrayHasKey('mock_gateway', $allProviders);
        $this->assertInstanceOf(MockPaymentGateway::class, $allProviders['mock_gateway']);
    }

    public function test_it_handles_empty_provider_list()
    {
        // Arrange - Registry already created with empty config

        // Act
        $allProviders = $this->registry->all();

        // Assert
        $this->assertIsArray($allProviders);
        $this->assertEmpty($allProviders);
    }

    public function test_it_handles_multiple_providers_from_config()
    {
        // Arrange
        $mockProvider1 = Mockery::mock(PaymentGatewayInterface::class);
        $mockProvider1->shouldReceive('getCode')->andReturn('provider1');

        $mockProvider2 = Mockery::mock(PaymentGatewayInterface::class);
        $mockProvider2->shouldReceive('getCode')->andReturn('provider2');

        // Mock the app container to return our mocked providers
        $this->app->bind('MockProvider1', function () use ($mockProvider1) {
            return $mockProvider1;
        });
        $this->app->bind('MockProvider2', function () use ($mockProvider2) {
            return $mockProvider2;
        });

        config(['kompo-finance.payment_providers' => [
            'MockProvider1',
            'MockProvider2',
            MockPaymentGateway::class,
        ]]);

        // Act
        $registry = new PaymentProviderRegistry();
        $allProviders = $registry->all();

        // Assert
        $this->assertCount(3, $allProviders);
        $this->assertArrayHasKey('provider1', $allProviders);
        $this->assertArrayHasKey('provider2', $allProviders);
        $this->assertArrayHasKey('mock_gateway', $allProviders);
    }

    public function test_it_handles_provider_registration_with_duplicate_codes_in_order()
    {
        // Arrange
        $provider1 = Mockery::mock(PaymentGatewayInterface::class);
        $provider1->shouldReceive('getCode')->andReturn('duplicate_code');

        $provider2 = Mockery::mock(PaymentGatewayInterface::class);
        $provider2->shouldReceive('getCode')->andReturn('duplicate_code');

        $provider3 = new MockPaymentGateway();

        // Act
        $this->registry->register($provider1);
        $this->registry->register($provider3);
        $this->registry->register($provider2); // This should overwrite provider1

        $allProviders = $this->registry->all();

        // Assert
        $this->assertCount(2, $allProviders); // Only 2 unique codes
        $this->assertSame($provider2, $allProviders['duplicate_code']); // Last one wins
        $this->assertSame($provider3, $allProviders['mock_gateway']);
    }
}

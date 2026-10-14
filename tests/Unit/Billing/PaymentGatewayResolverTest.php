<?php

namespace Condoedge\Finance\Tests\Unit\Billing;

use Condoedge\Finance\Billing\Contracts\PayableInterface;
use Condoedge\Finance\Billing\Contracts\ProviderHealthCheckerInterface;
use Condoedge\Finance\Billing\Core\PaymentContext;
use Condoedge\Finance\Billing\Core\PaymentOutcome;
use Condoedge\Finance\Billing\Core\ProviderHealthState;
use Condoedge\Finance\Billing\Core\ProviderHealthStatus;
use Condoedge\Finance\Billing\Core\Resolver\DefaultPaymentGatewayResolver;
use Condoedge\Finance\Billing\Exceptions\NoProviderAvailableException;
use Condoedge\Finance\Casts\SafeDecimal;
use Condoedge\Finance\Models\PaymentMethodEnum;
use Mockery;
use Tests\Mocks\MockPaymentGateway;
use Tests\TestCase;

class SecondMockGateway extends MockPaymentGateway
{
    public function getCode(): string
    {
        return 'second_gateway';
    }

    public function getSupportedPaymentMethods(): array
    {
        return [PaymentMethodEnum::CREDIT_CARD, PaymentMethodEnum::INTERAC];
    }
}

class PaymentGatewayResolverTest extends TestCase
{
    private DefaultPaymentGatewayResolver $resolver;

    /** @var array<string, ProviderHealthState> */
    private array $health = [];

    public function setUp(): void
    {
        parent::setUp();

        $health = &$this->health;
        $checker = new class ($health) implements ProviderHealthCheckerInterface {
            public function __construct(private array &$states) {}
            public function isHealthy(string $providerCode): bool
            {
                return $this->status($providerCode)->isUsable();
            }
            public function status(string $providerCode): ProviderHealthStatus
            {
                return new ProviderHealthStatus($providerCode, $this->states[$providerCode] ?? ProviderHealthState::HEALTHY);
            }
            public function record(string $providerCode, PaymentOutcome $outcome): void {}
        };

        $this->resolver = new DefaultPaymentGatewayResolver($checker);
        config(['kompo-finance.offer_fallback_provider_methods' => false]);
    }

    public function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_a_single_class_is_a_one_provider_chain()
    {
        config(['kompo-finance.payment_method_providers' => [
            PaymentMethodEnum::CREDIT_CARD->value => MockPaymentGateway::class,
        ]]);

        $this->assertSame('mock_gateway', $this->resolver->resolve($this->context(PaymentMethodEnum::CREDIT_CARD))->getCode());
        $this->assertSame(['mock_gateway'], $this->codes($this->resolver->previewChain($this->context(PaymentMethodEnum::CREDIT_CARD))));
    }

    public function test_a_list_is_an_ordered_fallback_chain()
    {
        config(['kompo-finance.payment_method_providers' => [
            PaymentMethodEnum::CREDIT_CARD->value => [SecondMockGateway::class, MockPaymentGateway::class],
        ]]);

        $this->assertSame(['second_gateway', 'mock_gateway'], $this->codes($this->resolver->previewChain($this->context(PaymentMethodEnum::CREDIT_CARD))));
    }

    public function test_degraded_providers_go_last_and_down_providers_are_skipped()
    {
        config(['kompo-finance.payment_method_providers' => [
            PaymentMethodEnum::CREDIT_CARD->value => [SecondMockGateway::class, MockPaymentGateway::class],
        ]]);
        $context = $this->context(PaymentMethodEnum::CREDIT_CARD);

        $this->health['second_gateway'] = ProviderHealthState::DEGRADED;
        $this->assertSame(['mock_gateway', 'second_gateway'], $this->codes($this->resolver->previewChain($context)));

        $this->health['second_gateway'] = ProviderHealthState::DOWN;
        $this->assertSame(['mock_gateway'], $this->codes($this->resolver->previewChain($context)));
        $this->assertSame(['second_gateway', 'mock_gateway'], $this->codes($this->resolver->getAvailableGateways($context)), 'Configured gateways ignore health.');
    }

    public function test_a_provider_that_cannot_take_the_method_is_dropped()
    {
        config(['kompo-finance.payment_method_providers' => [
            PaymentMethodEnum::INTERAC->value => [MockPaymentGateway::class, SecondMockGateway::class],
        ]]);

        $this->assertSame(['second_gateway'], $this->codes($this->resolver->getAvailableGateways($this->context(PaymentMethodEnum::INTERAC))));
    }

    public function test_an_unmapped_method_is_not_configured_and_a_down_chain_is_all_down()
    {
        config(['kompo-finance.payment_method_providers' => [
            PaymentMethodEnum::CREDIT_CARD->value => MockPaymentGateway::class,
        ]]);

        try {
            $this->resolver->resolveChain($this->context(PaymentMethodEnum::INTERAC));
            $this->fail('Expected NoProviderAvailableException');
        } catch (NoProviderAvailableException $e) {
            $this->assertSame('none_configured', $e->reason);
        }

        $this->health['mock_gateway'] = ProviderHealthState::DOWN;
        try {
            $this->resolver->resolveChain($this->context(PaymentMethodEnum::CREDIT_CARD));
            $this->fail('Expected NoProviderAvailableException');
        } catch (NoProviderAvailableException $e) {
            $this->assertSame('all_down', $e->reason);
            $this->assertSame(['mock_gateway'], $e->context['provider_codes']);
        }
    }

    public function test_a_method_served_only_by_a_fallback_provider_is_hidden_unless_configured_otherwise()
    {
        config(['kompo-finance.payment_method_providers' => [
            PaymentMethodEnum::CREDIT_CARD->value => [MockPaymentGateway::class, SecondMockGateway::class],
            PaymentMethodEnum::INTERAC->value => [SecondMockGateway::class],
        ]]);
        // second_gateway heads the Interac chain, so it is primary.
        $this->assertTrue($this->resolver->isMethodAvailable($this->context(PaymentMethodEnum::INTERAC)));

        config(['kompo-finance.payment_method_providers' => [
            PaymentMethodEnum::CREDIT_CARD->value => [MockPaymentGateway::class, SecondMockGateway::class],
            PaymentMethodEnum::INTERAC->value => [MockPaymentGateway::class, SecondMockGateway::class],
        ]]);
        // mock_gateway cannot take Interac; second_gateway heads no chain.
        $this->assertFalse($this->resolver->isMethodAvailable($this->context(PaymentMethodEnum::INTERAC)));

        config(['kompo-finance.offer_fallback_provider_methods' => true]);
        $this->assertTrue($this->resolver->isMethodAvailable($this->context(PaymentMethodEnum::INTERAC)));
    }

    private function codes(array $providers): array
    {
        return array_map(fn ($p) => $p->getCode(), $providers);
    }

    private function context(PaymentMethodEnum $method): PaymentContext
    {
        return new PaymentContext(payable: $this->createMockPayable(), paymentMethod: $method);
    }

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

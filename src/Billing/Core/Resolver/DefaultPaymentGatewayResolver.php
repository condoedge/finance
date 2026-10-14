<?php

namespace Condoedge\Finance\Billing\Core\Resolver;

use Condoedge\Finance\Billing\Contracts\PaymentGatewayInterface;
use Condoedge\Finance\Billing\Contracts\PaymentGatewayResolverInterface;
use Condoedge\Finance\Billing\Contracts\ProviderHealthCheckerInterface;
use Condoedge\Finance\Billing\Core\PaymentContext;
use Condoedge\Finance\Billing\Core\ProviderHealthState;
use Condoedge\Finance\Billing\Exceptions\NoProviderAvailableException;
use Condoedge\Finance\Models\PaymentMethodEnum;

/**
 * Resolves the project-wide fallback chain of providers for a payment method.
 *
 * The chain comes from config('kompo-finance.payment_method_providers'): one provider
 * class (single) or an ordered list (fallback). Credentials are each provider's env config.
 *
 * Health filter:
 *  - HEALTHY providers go first.
 *  - DEGRADED providers go last (still attempted).
 *  - DOWN providers are skipped.
 */
class DefaultPaymentGatewayResolver implements PaymentGatewayResolverInterface
{
    public function __construct(
        private ProviderHealthCheckerInterface $healthChecker,
    ) {
    }

    public function resolve(PaymentContext $context): PaymentGatewayInterface
    {
        $chain = $this->resolveChain($context);
        $chainArray = is_array($chain) ? $chain : iterator_to_array($chain);

        return $chainArray[0];
    }

    public function resolveChain(PaymentContext $context): iterable
    {
        $chain = $this->previewChain($context);

        if (empty($chain)) {
            // Distinguish "nothing configured" from "all dead" for better error messages.
            $configured = $this->getAvailableGateways($context);
            if (empty($configured)) {
                throw NoProviderAvailableException::noneConfigured(
                    $context->getTeamId(),
                    $context->paymentMethod,
                );
            }

            throw NoProviderAvailableException::allDown(
                $context->getTeamId(),
                $context->paymentMethod,
                array_map(fn (PaymentGatewayInterface $p) => $p->getCode(), $configured),
            );
        }

        return $chain;
    }

    public function previewChain(PaymentContext $context): array
    {
        $healthy = [];
        $degraded = [];
        foreach ($this->getAvailableGateways($context) as $provider) {
            match ($this->healthChecker->status($provider->getCode())->state) {
                ProviderHealthState::HEALTHY => $healthy[] = $provider,
                ProviderHealthState::DEGRADED => $degraded[] = $provider,
                ProviderHealthState::DOWN => null, // skip
            };
        }

        return array_merge($healthy, $degraded);
    }

    public function getAvailableGateways(PaymentContext $context): array
    {
        // Capability enforcement: a map line pointing at a provider that cannot
        // serve the method (e.g. interac -> stripe) is ignored.
        return array_values(array_filter(
            $this->configuredProviders($context->paymentMethod),
            fn (PaymentGatewayInterface $p) => in_array($context->paymentMethod, $p->getSupportedPaymentMethods(), true),
        ));
    }

    public function isMethodAvailable(PaymentContext $context): bool
    {
        // previewChain is health-filtered and capability-filtered, so an empty
        // chain means no healthy provider can serve this method at all.
        $chain = $this->previewChain($context);
        if (empty($chain)) {
            return false;
        }

        // Config ON: any healthy, capable provider in the chain is enough —
        // fallback-position providers contribute their methods too.
        if (config('kompo-finance.offer_fallback_provider_methods', false)) {
            return true;
        }

        // Config OFF: the method only shows if a "primary" provider serves it.
        // A provider is primary when it is first in any method's chain. This is what
        // hides e.g. Interac when BNA is only a credit-card fallback.
        $primaryCodes = $this->primaryProviderCodes();

        foreach ($chain as $provider) {
            if (in_array($provider->getCode(), $primaryCodes, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<PaymentGatewayInterface>
     */
    private function configuredProviders(PaymentMethodEnum $method): array
    {
        $classes = (array) (config('kompo-finance.payment_method_providers', [])[$method->value] ?? []);

        return array_values(array_filter(
            array_map(fn ($class) => app($class), $classes),
            fn ($provider) => $provider instanceof PaymentGatewayInterface,
        ));
    }

    /**
     * Codes of the providers that head a chain.
     *
     * @return array<string>
     */
    private function primaryProviderCodes(): array
    {
        $codes = [];
        foreach (config('kompo-finance.payment_method_providers', []) as $classes) {
            $first = ((array) $classes)[0] ?? null;
            if ($first && ($provider = app($first)) instanceof PaymentGatewayInterface) {
                $codes[] = $provider->getCode();
            }
        }

        return array_values(array_unique($codes));
    }
}

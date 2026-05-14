<?php

namespace Condoedge\Finance\Billing\Contracts;

use Condoedge\Finance\Billing\Core\PaymentContext;

interface PaymentGatewayResolverInterface
{
    /**
     * First provider in the resolved chain. Backwards-compatible with the
     * original single-resolve API. Throws NoProviderAvailableException if
     * the chain is empty.
     */
    public function resolve(PaymentContext $context): PaymentGatewayInterface;

    /**
     * Ordered providers to attempt. Healthy first, then degraded (if any).
     * DOWN providers are skipped. Single-mode rows return at most 1.
     * Throws NoProviderAvailableException if empty.
     *
     * @return iterable<PaymentGatewayInterface>
     */
    public function resolveChain(PaymentContext $context): iterable;

    /**
     * Same as resolveChain() but never throws — returns empty array when no
     * usable provider is available. Used by the pre-form gate to decide
     * whether to render the payment form or a "system unavailable" notice.
     *
     * @return array<PaymentGatewayInterface>
     */
    public function previewChain(PaymentContext $context): array;

    /**
     * All providers in the registry whose capabilities (getSupportedPaymentMethods)
     * include the context's payment method. Ignores team configuration and health.
     * Primarily useful for admin UIs that list "providers you could enable here".
     */
    public function getAvailableGateways(PaymentContext $context): array;

    /**
     * Whether the context's payment method should be offered to the customer.
     *
     * True when a healthy provider that genuinely supports the method is
     * resolvable AND either:
     *   - config('kompo-finance.offer_fallback_provider_methods') is on, or
     *   - a "primary" provider (priority 1 in any team row) serves the method.
     *
     * Drives the payment-method picker in InvoicePayModal so customers only see
     * methods their team's providers can actually process.
     */
    public function isMethodAvailable(PaymentContext $context): bool;
}

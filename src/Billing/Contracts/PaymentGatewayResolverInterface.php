<?php

namespace Condoedge\Finance\Billing\Contracts;

use Condoedge\Finance\Billing\Core\PaymentContext;

interface PaymentGatewayResolverInterface
{
    /**
     * Resolve which payment gateway to use for given context
     */
    public function resolve(PaymentContext $context): PaymentGatewayInterface;

    /**
     * Get all available gateways for given context
     */
    public function getAvailableGateways(PaymentContext $context): array;
}

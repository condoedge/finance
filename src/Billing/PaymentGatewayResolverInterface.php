<?php
namespace Condoedge\Finance\Billing;

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
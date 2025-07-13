<?php
namespace Condoedge\Finance\Billing;


class DefaultPaymentGatewayResolver implements PaymentGatewayResolverInterface
{
    public function __construct(
        private PaymentProviderRegistry $registry
    ) {}
    
    public function resolve(PaymentContext $context): PaymentGatewayInterface
    {
        // Current logic from PaymentMethodEnum
        $providerClass = $context->paymentMethod->getDefaultPaymentGateway();

        $provider = app()->make($providerClass);

        return $this->registry->get($provider->getCode());
    }
    
    public function getAvailableGateways(PaymentContext $context): array
    {
        $available = [];
        
        foreach ($this->registry->all() as $provider) {
            if (in_array($context->paymentMethod, $provider->getSupportedPaymentMethods())) {
                $available[] = $provider;
            }
        }
        
        return $available;
    }
}
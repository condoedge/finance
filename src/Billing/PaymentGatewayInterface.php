<?php

namespace Condoedge\Finance\Billing;

interface PaymentGatewayInterface
{
    /**
     * Optional: Initialize gateway with context
     *
     * This method is called by PaymentGatewayResolver::resolveWithContext()
     * to provide additional context to the gateway
     */
    public function initializeContext(array $context = []): void;


    public function executeSale($request, $onSuccess = null);

}

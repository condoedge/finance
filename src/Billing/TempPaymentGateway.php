<?php

namespace Condoedge\Finance\Billing;

use Condoedge\Finance\Billing\PaymentGatewayInterface;
use Condoedge\Finance\Models\Account;
use Illuminate\Support\Facades\Log;

class TempPaymentGateway implements PaymentGatewayInterface
{
    protected array $context = [];

    public function getCashAccount(): Account 
    {
        return Account::latest()->first();
    }

    /**
     * Initialize gateway with context
     */
    public function initializeContext(array $context = []): void
    {
        $this->context = $context;
        
        Log::info('TempPaymentGateway initialized with context', [
            'context_keys' => array_keys($context),
        ]);
    }
    
    public function refundOrder() 
    {
        Log::info('Refunding order...', [
            'gateway_context' => $this->context,
        ]);
    }
    
    public function setRoutes() 
    {
        Log::info('Setting routes...', [
            'gateway_context' => $this->context,
        ]);
    }

    /**
     * Get current context
     */
    public function getContext(): array
    {
        return $this->context;
    }
}

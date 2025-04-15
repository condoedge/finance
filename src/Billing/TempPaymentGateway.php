<?php

namespace Condoedge\Finance\Billing;

use Condoedge\Finance\Billing\PaymentGatewayInterface;
use Condoedge\Finance\Models\Account;
use Illuminate\Support\Facades\Log;

class TempPaymentGateway implements PaymentGatewayInterface
{
    public function getCashAccount(): Account 
    {
        return Account::latest()->first();
    }
    
    public function refundOrder() 
    {
        Log::info('Refunding order...');
    }
    
    public function setRoutes() 
    {
        Log::info('Setting routes...');
    }
}
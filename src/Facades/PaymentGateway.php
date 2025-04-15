<?php

namespace Condoedge\Finance\Facades;

use Condoedge\Finance\Billing\PaymentGatewayInterface;
use Condoedge\Finance\Billing\PaymentGatewayResolver;

/**
 * @method static \Condoedge\Finance\Models\Account getCashAccount()
 * @method static mixed setRoutes()
 * 
 * @mixin \Condoedge\Finance\Billing\PaymentGatewayInterface
 */
class PaymentGateway extends \Illuminate\Support\Facades\Facade
{
    protected static function getFacadeAccessor()
    {
        return PaymentGatewayInterface::class;
    }
}
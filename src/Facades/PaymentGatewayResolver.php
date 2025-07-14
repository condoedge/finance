<?php

namespace Condoedge\Finance\Facades;

use Condoedge\Finance\Billing\Contracts\PaymentGatewayResolverInterface;

/**
 * Payment Gateway Facade
 *
 *
 * @mixin \Condoedge\Finance\Billing\Contracts\PaymentGatewayResolverInterface
 */
class PaymentGatewayResolver extends \Illuminate\Support\Facades\Facade
{
    protected static $paymentGatewayService;

    protected static function getFacadeAccessor()
    {
        return PaymentGatewayResolverInterface::class;
    }
}

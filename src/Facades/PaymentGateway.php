<?php

namespace Condoedge\Finance\Facades;

use Condoedge\Finance\Services\PaymentGatewayService;

/**
 * Payment Gateway Facade
 *
 *
 * @mixin \Condoedge\Finance\Billing\PaymentGatewayInterface
 * @mixin \Condoedge\Finance\Services\PaymentGatewayService
 */
class PaymentGateway extends \Illuminate\Support\Facades\Facade
{
    protected static $paymentGatewayService;

    protected static function getFacadeAccessor()
    {
        return PaymentGatewayService::class;
    }
}

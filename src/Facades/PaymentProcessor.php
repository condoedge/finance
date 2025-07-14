<?php

namespace Condoedge\Finance\Facades;

use Condoedge\Finance\Billing\PaymentProcessorInterface;

/**
 * Payment Gateway Facade
 *
 *
 * @mixin \Condoedge\Finance\Billing\Contracts\PaymentProcessorInterface
 */
class PaymentProcessor extends \Illuminate\Support\Facades\Facade
{
    protected static $paymentGatewayService;

    protected static function getFacadeAccessor()
    {
        return PaymentProcessorInterface::class;
    }
}

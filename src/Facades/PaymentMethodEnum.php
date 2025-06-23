<?php

namespace Condoedge\Finance\Facades;

use Condoedge\Utils\Facades\FacadeEnum;

/**
 * @mixin \Condoedge\Finance\Models\PaymentMethodEnum
 */
class PaymentMethodEnum extends FacadeEnum
{
    protected static function getFacadeAccessor()
    {
        return PAYMENT_METHOD_ENUM_KEY;
    }
}
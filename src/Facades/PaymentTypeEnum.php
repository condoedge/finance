<?php

namespace Condoedge\Finance\Facades;

use Kompo\Auth\Facades\FacadeEnum;

/**
 * @mixin \Condoedge\Finance\Models\PaymentTypeEnum
 */
class PaymentTypeEnum extends FacadeEnum
{
    protected static function getFacadeAccessor()
    {
        return PAYMENT_TYPE_ENUM_KEY;
    }
}
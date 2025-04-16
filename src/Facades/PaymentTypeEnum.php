<?php

namespace Condoedge\Finance\Facades;

use Condoedge\Utils\Facades\FacadeEnum;

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
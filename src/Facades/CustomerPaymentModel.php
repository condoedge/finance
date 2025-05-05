<?php

namespace Condoedge\Finance\Facades;

use Kompo\Komponents\Form\KompoModelFacade;

/**
 * @mixin \Condoedge\Finance\Models\CustomerPayment
 */
class CustomerPaymentModel extends KompoModelFacade
{
    protected static function getModelBindKey()
    {
        return CUSTOMER_PAYMENT_MODEL_KEY;
    }
}
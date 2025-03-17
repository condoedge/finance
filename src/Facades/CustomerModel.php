<?php

namespace Condoedge\Finance\Facades;

use Kompo\Komponents\Form\KompoModelFacade;

/**
 * @mixin \Condoedge\Finance\Models\Customer
 */
class CustomerModel extends KompoModelFacade
{
    protected static function getModelBindKey()
    {
        return CUSTOMER_MODEL_KEY;
    }
}
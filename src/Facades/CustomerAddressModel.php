<?php

namespace Condoedge\Finance\Facades;

use Kompo\Komponents\Form\KompoModelFacade;

/**
 * @mixin \Condoedge\Finance\Models\CustomerAddress
 */
class CustomerAddressModel extends KompoModelFacade
{
    protected static function getModelBindKey()
    {
        return CUSTOMER_ADDRESS_MODEL_KEY;
    }
}
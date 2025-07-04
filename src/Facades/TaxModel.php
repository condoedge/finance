<?php

namespace Condoedge\Finance\Facades;

use Kompo\Komponents\Form\KompoModelFacade;

/**
 * @mixin \Condoedge\Finance\Models\Tax
 */
class TaxModel extends KompoModelFacade
{
    protected static function getModelBindKey()
    {
        return TAX_MODEL_KEY;
    }
}

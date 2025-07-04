<?php

namespace Condoedge\Finance\Facades;

use Kompo\Komponents\Form\KompoModelFacade;

/**
 * @mixin \Condoedge\Finance\Models\TaxGroup
 */
class TaxGroupModel extends KompoModelFacade
{
    protected static function getModelBindKey()
    {
        return TAX_GROUP_MODEL_KEY;
    }
}

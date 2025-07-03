<?php

namespace Condoedge\Finance\Facades;

use Kompo\Komponents\Form\KompoModelFacade;

/**
 * @mixin \Condoedge\Finance\Models\Product
 */
class ProductModel extends KompoModelFacade
{
    protected static function getModelBindKey()
    {
        return PRODUCT_MODEL_KEY;
    }
}
<?php

namespace Condoedge\Finance\Facades;

use Condoedge\Utils\Facades\FacadeEnum;

/**
 * @mixin \Condoedge\Finance\Models\ProductTypeEnum
 */
class ProductTypeEnum extends FacadeEnum
{
    protected static function getFacadeAccessor()
    {
        return PRODUCT_TYPE_ENUM_KEY;
    }
}

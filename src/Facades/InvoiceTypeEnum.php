<?php

namespace Condoedge\Finance\Facades;

use Condoedge\Utils\Facades\FacadeEnum;

/**
 * @mixin \Condoedge\Finance\Models\InvoiceTypeEnum
 */
class InvoiceTypeEnum extends FacadeEnum
{
    protected static function getFacadeAccessor()
    {
        return INVOICE_TYPE_ENUM_KEY;
    }
}

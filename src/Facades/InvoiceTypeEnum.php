<?php

namespace Condoedge\Finance\Facades;

use Kompo\Auth\Facades\FacadeEnum;

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
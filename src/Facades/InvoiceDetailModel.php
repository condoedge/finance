<?php

namespace Condoedge\Finance\Facades;

use Kompo\Komponents\Form\KompoModelFacade;

/**
 * @mixin \Condoedge\Finance\Models\InvoiceDetail
 */
class InvoiceDetailModel extends KompoModelFacade
{
    protected static function getModelBindKey()
    {
        return INVOICE_DETAIL_MODEL_KEY;
    }
}
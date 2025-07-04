<?php

namespace Condoedge\Finance\Facades;

use Kompo\Komponents\Form\KompoModelFacade;

/**
 * @mixin \Condoedge\Finance\Models\Invoice
 */
class InvoiceModel extends KompoModelFacade
{
    protected static function getModelBindKey()
    {
        return INVOICE_MODEL_KEY;
    }
}

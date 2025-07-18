<?php

namespace Condoedge\Finance\Facades;

use Kompo\Komponents\Form\KompoModelFacade;

/**
 * @mixin \Condoedge\Finance\Models\InvoiceApply
 */
class InvoicePaymentModel extends KompoModelFacade
{
    protected static function getModelBindKey()
    {
        return INVOICE_PAYMENT_MODEL_KEY;
    }
}

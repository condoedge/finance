<?php

namespace Condoedge\Finance\Facades;

use Kompo\Komponents\Form\KompoModelFacade;

/**
 * @mixin \Condoedge\Finance\Models\InvoiceDetail
 *
 * @see \Condoedge\Finance\Models\InvoiceDetail
 *
 * @method static \Condoedge\Finance\Models\InvoiceDetail findOrFail()
 * @method static \Condoedge\Finance\Models\InvoiceDetail find()
 * @method static \Condoedge\Finance\Models\InvoiceDetail first()
 * @method static \Condoedge\Finance\Models\InvoiceDetail firstOrFail()
 * @method static \Condoedge\Finance\Models\InvoiceDetail firstOrNew()
 * @method static \Condoedge\Finance\Models\InvoiceDetail firstOrCreate()
 * @method static \Condoedge\Finance\Models\InvoiceDetail create()
 * @method static \Illuminate\Database\Eloquent\Collection<\Condoedge\Finance\Models\InvoiceDetail> all()
 * @method static \Illuminate\Database\Eloquent\Collection<\Condoedge\Finance\Models\InvoiceDetail> get()
 */
class InvoiceDetailModel extends KompoModelFacade
{
    protected static function getModelBindKey()
    {
        return INVOICE_DETAIL_MODEL_KEY;
    }
}

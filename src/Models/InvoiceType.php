<?php

namespace Condoedge\Finance\Models;

use Condoedge\Finance\Facades\InvoiceModel;
use Condoedge\Utils\Models\Model;
use Kompo\Database\HasTranslations;

class InvoiceType extends Model
{
    use HasTranslations;

    protected $translatable = ['name'];

    protected $table = 'fin_invoice_types';

    /* RELATIONSHIPS */
    public function invoices()
    {
        return $this->hasMany(InvoiceModel::getClass(), 'invoice_type_id');
    }

    /* CALCULATED ATTRIBUTES */
    public function getEnum()
    {
        return InvoiceTypeEnum::from($this->id);
    }
}

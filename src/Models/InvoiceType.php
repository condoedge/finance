<?php

namespace Condoedge\Finance\Models;

use Condoedge\Utils\Models\Model;

class InvoiceType extends Model
{
    protected $table = 'fin_invoice_types';

    /* RELATIONSHIPS */
    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'invoice_type_id');
    }

    /* CALCULATED ATTRIBUTES */
    public function getEnum()
    {
        return InvoiceTypeEnum::from($this->id);
    }
}

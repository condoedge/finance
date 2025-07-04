<?php

namespace Condoedge\Finance\Models;

use Condoedge\Utils\Models\Model;

class InvoiceStatus extends Model
{
    protected $table = 'fin_invoice_statuses';

    /* RELATIONSHIPS */
    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'invoice_status_id');
    }

    /* CALCULATED ATTRIBUTES */
    public function getEnum()
    {
        return InvoiceStatusEnum::from($this->id);
    }
}

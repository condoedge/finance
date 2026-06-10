<?php

namespace Condoedge\Finance\Models;

use Condoedge\Finance\Facades\InvoiceModel;
use Condoedge\Utils\Models\Model;
use Kompo\Database\HasTranslations;

class InvoiceStatus extends Model
{
    use HasTranslations;
    protected $translatable = ['name'];
    protected $table = 'fin_invoice_statuses';

    /* RELATIONSHIPS */
    public function invoices()
    {
        return $this->hasMany(InvoiceModel::getClass(), 'invoice_status_id');
    }

    /* CALCULATED ATTRIBUTES */
    public function getEnum()
    {
        return InvoiceStatusEnum::from($this->id);
    }
}

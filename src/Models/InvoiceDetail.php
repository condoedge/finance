<?php

namespace Condoedge\Finance\Models;

use Condoedge\Finance\Events\InvoiceDetailGenerated;

class InvoiceDetail extends AbstractMainFinanceModel
{
    protected $table = 'fin_invoice_details';

    public function getCreatedEventClass()
    {
        return InvoiceDetailGenerated::class;
    }

    /* RELATIONSHIPS */
    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    /* ATTRIBUTES */

    /* CALCULATED FIELDS */

    /* SCOPES */

    /* ACTIONS */

    /* INTEGRITY */
    public static function checkIntegrity($ids = null): void
    {
        // Implement specific integrity check for the InvoiceDetail model. For now, we don't have any.
    }

    /* ELEMENTS */    
}
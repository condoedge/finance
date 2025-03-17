<?php

namespace Condoedge\Finance\Models;

class InvoicePayment extends AbstractMainFinanceModel
{
    protected $table = 'fin_invoice_payments';

    /* RELATIONSHIPS */
    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    /* INTEGRITY */
    public static function checkIntegrity($ids = null): void
    {
        // Implement specific integrity check for the InvoicePayment model. For now, we don't have any.
    }
}
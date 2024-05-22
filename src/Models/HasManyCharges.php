<?php 

namespace Condoedge\Finance\Models;

trait HasManyCharges
{
    /* RELATIONS */
    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function bills()
    {
        return $this->hasMany(Bill::class);
    }

    public function invoiceItems()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function billItems()
    {
        return $this->hasMany(BillItem::class);
    }

    /* ACTIONS */

    /* SCOPES */

}
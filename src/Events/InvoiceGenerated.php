<?php

namespace Condoedge\Finance\Events;

class InvoiceGenerated
{
    protected $invoice;

    public function __construct($invoice)
    {
        $this->invoice = $invoice;
    }

    public function getInvoice()
    {
        return $this->invoice;
    }
}

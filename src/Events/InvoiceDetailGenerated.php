<?php

namespace Condoedge\Finance\Events;

class InvoiceDetailGenerated
{
    protected $invoiceDetail;
    
    public function __construct($invoiceDetail)
    {
        $this->invoiceDetail = $invoiceDetail;
    }

    public function getInvoiceDetail()
    {
        return $this->invoiceDetail;
    }
}
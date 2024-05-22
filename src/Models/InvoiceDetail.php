<?php

namespace Condoedge\Finance\Models;

class InvoiceDetail extends ChargeDetail
{
    /* RELATIONSHIPS */
    public function invoice()
    {
    	return $this->belongsTo(Invoice::class);
    }
    
    public function fund()
    {
        return $this->belongsTo(Fund::class);
    }

}

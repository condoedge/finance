<?php

namespace Condoedge\Finance\Models;

class InvoiceDetail extends ChargeDetail
{
	protected $table = 'charge_details';

    /* RELATIONSHIPS */
    public function fund()
    {
        return $this->belongsTo(Fund::class);
    }

}

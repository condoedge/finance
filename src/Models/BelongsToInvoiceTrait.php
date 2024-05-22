<?php 

namespace Condoedge\Finance\Models;

trait BelongsToInvoiceTrait
{
	/* RELATIONS */
    public function invoice()
	{
		return $this->belongsTo(Invoice::class);
	}
}
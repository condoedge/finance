<?php 

namespace Condoedge\Finance\Models;

trait BelongsToInvoiceTrait
{
	/* RELATIONS */
    public function invoice()
	{
		return $this->belongsTo(Invoice::class);
	}

    /* SCOPES */
    public function scopeForInvoice($query, $idOrIds)
    {
        scopeWhereBelongsTo($query, 'invoice_id', $idOrIds);
    }

	/* CALCULATED FIELDS */

	/* ACTIONS */

	/* ELEMENTS */
}
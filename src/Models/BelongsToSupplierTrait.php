<?php 

namespace Condoedge\Finance\Models;

trait BelongsToSupplierTrait
{
	/* RELATIONS */
    public function supplier()
	{
		return $this->belongsTo(Supplier::class);
	}
}
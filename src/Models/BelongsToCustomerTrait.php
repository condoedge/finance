<?php 

namespace Condoedge\Finance\Models;

trait BelongsToCustomerTrait
{
	/* RELATIONS */
    public function customer()
	{
		return $this->belongsTo(Customer::class);
	}
}
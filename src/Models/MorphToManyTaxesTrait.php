<?php 

namespace Condoedge\Finance\Models;

trait MorphToManyTaxesTrait
{
	/* RELATIONS */
    public function taxes()
	{
		return $this->morphToMany(Tax::class, 'taxable', 'taxable_tax')->withTimestamps();
	}
}
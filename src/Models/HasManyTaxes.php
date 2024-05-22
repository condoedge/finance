<?php 

namespace Condoedge\Finance\Models;

use Condoedge\Finance\Models\Tax;

trait HasManyTaxes
{
    /* RELATIONS */
    public function taxes()
    {
        return $this->hasMany(Tax::class);
    }

    /* CALCULATED FIELDS */

    /* ACTIONS */

    /* SCOPES */

}
<?php

namespace Condoedge\Finance\Models;

use Condoedge\Utils\Models\Model;

class Tax extends Model
{
    protected $table = 'fin_taxes';
    
    /* RELATIONSHIPS */
    public function groups()
    {
        return $this->belongsToMany(TaxGroup::class, 'fin_taxes_group_taxes', 'tax_id', 'tax_group_id');
    }

    /* ATTRIBUTES */

    /* CALCULATED FIELDS */

    /* SCOPES */
    public function scopeActive($query)
    {
        return $query->where('valide_from', '<=', now())->where('valide_to', '>=', now());
    }

    /* ACTIONS */

    /* ELEMENTS */    
}
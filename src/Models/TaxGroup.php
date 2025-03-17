<?php

namespace Condoedge\Finance\Models;

use Kompo\Auth\Models\Model;

class TaxGroup extends Model
{
    protected $table = 'fin_taxes_groups';
    
    /* RELATIONSHIPS */
    public function taxes()
    {
        return $this->belongsToMany(Tax::class, 'fin_tax_group_tax', 'tax_group_id', 'tax_id');
    }
}
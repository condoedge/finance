<?php

namespace Condoedge\Finance\Models;

use Condoedge\Utils\Models\Model;

class TaxGroup extends Model
{
    protected $table = 'fin_taxes_groups';
    
    /* RELATIONSHIPS */
    public function taxes()
    {
        return $this->belongsToMany(Tax::class, 'fin_taxes_group_taxes', 'tax_group_id', 'tax_id');
    }
}
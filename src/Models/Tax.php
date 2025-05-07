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
    public function getCompleteLabelAttribute()
    {
        return $this->name . ' (' . $this->rate * 100 . '%)';
    }

    
    public function getCompleteLabelHtmlAttribute()
    {
        return '<span data-name="'.$this->name.'" data-tax="'.$this->rate.'" data-id="'.$this->id.'">'.$this->complete_label.'</span>';
    }

    /* CALCULATED FIELDS */

    /* SCOPES */
    public function scopeActive($query)
    {
        return $query->where('valide_from', '<=', now())->where('valide_to', '>=', now());
    }

    /* ACTIONS */

    /* ELEMENTS */    
}
<?php

namespace Condoedge\Finance\Models;

use App\Models\Condo\Unit;
use Condoedge\Utils\Models\Model;

class FundQuote extends Model
{
    /* RELATIONSHIPS */
    public function fund()
    {
        return $this->belongsTo(Fund::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    /* ATTRIBUTES */

    /* CALCULATED FIELDS */

    /* ACTIONS */    
    
}

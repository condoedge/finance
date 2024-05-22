<?php

namespace Condoedge\Finance\Models;

use App\Models\Condo\Unit;
use App\Models\Model;

class BudgetDetailQuote extends Model
{
    /* RELATIONSHIPS */
    public function budgetDetail()
    {
        return $this->belongsTo(BudgetDetail::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    /* ATTRIBUTES */

    /* CALCULATED FIELDS */

    /* ACTIONS */    
    
}

<?php

namespace Condoedge\Finance\Models;

use Kompo\Auth\Models\Model;

class FundDate extends Model
{
    /* RELATIONSHIPS */
    public function fund()
    {
        return $this->belongsTo(Fund::class);
    }

    /* ATTRIBUTES */

    /* CALCULATED FIELDS */
    public static function months()
    {
    	$months = [1,2,3,4,5,6,7,8,9,10,11,12];

    	return collect($months)->mapWithKeys(fn($month) => [
    		$month => __('Month').' '.$month,
    	]);
    }

    /* ACTIONS */

}

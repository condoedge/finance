<?php

namespace Condoedge\Finance\Models;

use Kompo\Auth\Models\Model;

class BillItem extends Model
{
    use \Kompo\Auth\Models\Teams\BelongsToTeamTrait;

    /* RELATIONSHIPS */
    public function account()
    {
        return $this->belongsTo(GlAccount::class);
    }

    public function taxes()
    {
    	return $this->belongsToMany(Tax::class);
    }

    /* ATTRIBUTES */

    /* CALCULATED FIELDS */

    /* ACTIONS */
    public function delete()
    {
        $this->taxes()->sync([]);

        parent::delete();
    }

    /* ELEMENTS */
}

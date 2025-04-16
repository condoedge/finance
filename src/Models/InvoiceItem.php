<?php

namespace Condoedge\Finance\Models;

use Condoedge\Utils\Models\Model;

class InvoiceItem extends Model
{
    use \Kompo\Auth\Models\Traits\BelongsToUserTrait;
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

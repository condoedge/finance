<?php

namespace Condoedge\Finance\Models;

use App\Models\Model;
use App\Models\Crm\BelongsToUnion;
use Illuminate\Database\Eloquent\SoftDeletes;

class InvoiceItem extends Model
{
    use SoftDeletes, 
        BelongsToUnion;

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

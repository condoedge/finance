<?php

namespace Condoedge\Finance\Models;

use App\Models\Model;

class SubAccount extends Model
{
    public $fillable = [
        'gl_account_id',
    ];

    /* RELATIONSHIPS */
    public function glAccount()
    {
        return $this->belongsTo(GlAccount::class);
    }

    public function subaccountable()
    {
        return $this->morphTo();
    }

    /* ATTRIBUTES */
    public function getHasCreditAttribute()
    {
        return $this->credit_balance > 0 || $this->debit_balance > 0;
    }

    /* CALCULATED FIELDS */

    /* SCOPES */

    /* ACTIONS */

    /* ELEMENTS */
}

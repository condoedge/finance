<?php 

namespace Condoedge\Finance\Models;

use App\Models\Finance\GlAccount;

trait BelongsToGlAccountTrait
{
	/* RELATIONS */
    public function glAccount()
	{
		return $this->belongsTo(GlAccount::class);
	}

    /* SCOPES */
    public function scopeForGlAccount($query, $idOrIds)
    {
        scopeWhereBelongsTo($query, 'gl_account_id', $idOrIds);
    }

	/* CALCULATED FIELDS */

	/* ACTIONS */

	/* ELEMENTS */
}
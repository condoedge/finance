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

    public function scopeForTeamGlAccounts($query, $teamIdOrIds = null)
    {
    	$query->whereHas('glAccount', fn($q) => $q->forTeam($teamIdOrIds));
    }

	/* CALCULATED FIELDS */

	/* ACTIONS */

	/* ELEMENTS */
}
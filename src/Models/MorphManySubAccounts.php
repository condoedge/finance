<?php 

namespace Condoedge\Finance\Models;

use Condoedge\Finance\Models\SubAccount;

trait MorphManySubAccounts
{
    /* RELATIONS */
    public function subAccounts()
    {
        return $this->morphMany(SubAccount::class, 'subaccountable');
    }

    /* SCOPES */
    public function scopeForGlAccount($query, $accountId)
    {
        $query->where('gl_account_id', $accountId);
    }

    /* CALCULATED FIELDS */

    /* ACTIONS */
    public function createOrGetSubAccount($accountId)
    {
        $subAccount = $this->subAccounts()->forAccount($accountId)->first();

        if (!$subAccount) {
            $subAccount = new SubAccount();
            $subAccount->gl_account_id = $accountId;
            $this->subAccounts()->save($subAccount);
        }

        return $subAccount;
    }

}
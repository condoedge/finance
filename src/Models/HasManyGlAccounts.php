<?php

namespace Condoedge\Finance\Models;

use App\Models\Finance\GlAccount;

trait HasManyGlAccounts
{
    /* RELATIONS */
    public function glAccounts()
    {
        return $this->hasMany(GlAccount::class);
    }

    public function glAccount()
    {
        return $this->hasOne(GlAccount::class);
    }

    public function incomeAccount()
    {
        return $this->glAccount()->income();
    }

    public function bnrAccount()
    {
        return $this->glAccount()->bnr();
    }

    /* CALCULATED FIELDS */
    public function acceptsFinanceChange($date)
    {
        return $date >= '2023-07-24'; //TODO HARDCODED FOR NOW
    }

    public function checkIfDateAcceptable($date)
    {
        if ( !$this->acceptsFinanceChange($date) ) {
            throwValidationError('not_editable', balanceLockedMessage($this->latestBalanceDate()));
        }
    }

    public function latestBalanceDate()
    {
        return $this->glAccounts()->latest('updated_at')->value('updated_at');
    }

    /* ACTIONS */

    /* SCOPES */

}

<?php

namespace Condoedge\Finance\Models;

use App\Models\Finance\GlAccount;

trait HasManyGlAccountsForTeam
{
    use \Condoedge\Finance\Models\HasManyGlAccounts;

    /* RELATIONS */
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
        return $date >= $this->latestBalanceDate();
    }

    public function checkIfDateAcceptable($date)
    {
        if ( !$this->acceptsFinanceChange($date) ) {
            throwValidationError('not_editable', balanceLockedMessage($this->latestBalanceDate()));
        }
    }

    public function latestBalanceDate()
    {
        return AccountBalance::getLastLockedDate($this->id);
    }

    public function getLateDays()
    {
        return $this->late_days ?: 30; //todo
    }

    public function getFullGlAccountPrefix()
    {
        return $this->gl_account_prefix;
    }

    /* ACTIONS */

    /* SCOPES */

}

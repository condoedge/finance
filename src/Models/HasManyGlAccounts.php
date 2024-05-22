<?php

namespace Condoedge\Finance\Models;

trait HasManyGlAccounts
{
    /* RELATIONS */
    public function glAccounts()
    {
        return $this->hasMany(GlAccount::class);
    }

    public function funds()
    {
        return $this->hasMany(Fund::class);
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
    public function createFinancialData()
    {
        $this->createFundsIfNone();
        $this->createGlAccountsIfNone();
    }

    public function createFundsIfNone()
    {
        if ($this->funds()->count()) {
            return;
        }

        Fund::seed($this->id);
    }

    public function createGlAccountsIfNone()
    {
        if ($this->glAccounts()->count()) {
            return;
        }

        $unionFunds = $this->funds()->pluck('id', 'type_id');

        Rgcq::get()->each(
            fn($rgcq) => GlAccount::forceCreate([
                'union_id' => $this->id,
                'fund_id' => $rgcq->fund_type_id ? $unionFunds[$rgcq->fund_type_id] : null,
                'level' => $rgcq->level,
                'group' => $rgcq->group,
                'type' => $rgcq->getTranslations('type'),
                'name' => $rgcq->getTranslations('name'),
                'subname' => $rgcq->getTranslations('subname'),
                'description' => $rgcq->getTranslations('description'),
                'code' => $rgcq->code,
            ])
        );

        //Create tax accounts
        $this->team->taxes->each(fn($tax) => $tax->createTaxAccount($this->id));
    }

    /* SCOPES */

}

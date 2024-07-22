<?php

namespace Condoedge\Finance\Models;

use Kompo\Auth\Models\Model;

class Fund extends Model
{
    use \Kompo\Auth\Models\Teams\BelongsToTeamTrait;
    use \Condoedge\Finance\Models\HasManyGlAccounts;

    use \Kompo\Database\HasTranslations;
    protected $translatable = [
        'name',
        'description',
    ];

    public const TYPE_OPERATING = 1;
    public const TYPE_MAINTENANCE = 2;
    public const TYPE_CONTIGENCY = 3;
    public const TYPE_INSURANCE = 4;
    public const TYPE_CUSTOM = 5;
    public const TYPE_PARKING = 6;

    /* RELATIONSHIPS */
    public function budgetDetails()
    {
        return $this->hasMany(BudgetDetail::class);
    }

    public function fundQuotes()
    {
        return $this->hasMany(FundQuote::class);
    }

    public function fundDates()
    {
        return $this->hasMany(FundDate::class);
    }

    /* ATTRIBUTES */
    public function getAllocationAttribute()
    {
        $allocations = [
            __('finance.12months-allocation'),
            __('finance.manual-allocation'),
        ];

        return $allocations[$this->fundDates()->count() ? 1 : 0];
    }

    /* SCOPES */
    public function scopeIsDefaultFunds($query)
    {
        return $query->whereIn('type_id', static::defaultFundsTypeIds());
    }

    public function scopeNotDefaultFunds($query)
    {
        return $query->whereNotIn('type_id', static::defaultFundsTypeIds());
    }

    /* CALCULATED FIELDS */
    public function getRelatedAccounts($group)
    {
        $query = GlAccount::inUnionGl($this->union);

        if ($this->isDefaultFund()) {
            $query = $query->where('fund_id', $this->id);
        }

        return $query->where('group', $group);
    }

    public function isDefaultFund()
    {
        return static::defaultFundsTypeIds()->contains($this->type_id);
    }

    public function isOperatingFund()
    {
        return $this->type_id == static::TYPE_OPERATING;
    }

    public function getPctPerUnit($unit)
    {
        if (!$this->fundQuotes->count()) {
            return $unit->totalSharePct();
        }

        $totalFractions = $this->fundQuotes->sum('fractions');
        $unitFractions = $this->fundQuotes->where('customer_id', $unit->id)->sum('fractions');

        return $totalFractions ? ($unitFractions/$totalFractions) : $totalFractions;
    }

    public function getPctPerMonth($month)
    {
        $checkedDates = $this->fundDates->where('checked', 1);
        $fundDatesCount = $checkedDates->count();

        if (!$fundDatesCount) {
            return 1 / 12;
        }

        $isMonthChecked = $checkedDates->where('month', $month)->count();

        return $isMonthChecked ? (1 / $fundDatesCount) : 0;
    }

    /* ACTIONS */
    public function newFundIncomeAccount($type)
    {
        $lastSibling = GlAccount::getLastSibling(GlAccount::CODE_INCOME, $this->union_id);

        return $this->createRelatedAccount($type, __('Contribution fund'), GlAccount::getNextCode($lastSibling));
    }

    public function newFundBnrAccount($type)
    {
        $lastSibling = GlAccount::getLastSibling(GlAccount::CODE_BNR_SPECIAL, $this->union_id);

        return $this->createRelatedAccount($type, __('Surplus fund'), GlAccount::getNextCode($lastSibling));
    }

    protected function createRelatedAccount($type, $name, $code)
    {
        $account = new Account();
        $account->union_id = $this->union_id;
        $account->level = GlAccount::LEVEL_MEDIUM;
        $account->group = GlAccount::GROUP_INCOME;
        $account->type = $type;
        $account->name = $name.' '.$this->name;
        $account->subname = null;
        $account->code = $code;

        return $account;
    }

    public function delete()
    {
        if ($this->isDefaultFund()) {
            abort(403, __('finance.cant-delete-initial-funds'));
        }

        $this->unprotectedDelete();
    }

    public function unprotectedDelete()
    {
        $this->fundDates()->delete();
        $this->fundQuotes()->delete();

        $this->incomeAccount?->delete();
        $this->bnrAccount?->delete();

        parent::delete();
    }

    /* DATABASE */
    public static function seed($unionId)
    {
    	return collect(static::defaultFunds())
            ->map(function ($values) use($unionId) {
                return static::forceCreate([
                    'union_id' => $unionId,
                    'name' => translationsArr($values['name']),
                    'type_id' => $values['type_id'],
                ]);
            });
    }

    public static function defaultFundsTypeIds()
    {
        return collect(static::defaultFunds())->pluck('type_id');
    }

    public static function defaultFunds()
    {
    	return [
    		[
                'type_id' => static::TYPE_OPERATING,
                'name' => 'finance.operating-fund',
            ],
    		[
                'type_id' => static::TYPE_MAINTENANCE,
                'name' => 'finance.maintenance-fund',
            ],
    		[
                'type_id' => static::TYPE_CONTIGENCY,
                'name' => 'finance.contingency-fund',
            ],
    		[
                'type_id' => static::TYPE_INSURANCE,
                'name' => 'finance.insurance-fund',
            ],
    	];
    }
}

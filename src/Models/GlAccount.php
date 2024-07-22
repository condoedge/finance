<?php

namespace Condoedge\Finance\Models;

use App\Models\Finance\Bank;
use App\Models\Finance\Entry;

use Kompo\Auth\Models\Model;

class GlAccount extends Model
{
    use \Kompo\Auth\Models\Teams\BelongsToTeamTrait;
    use \Condoedge\Finance\Models\GlAccountCreateActionsTrait;
    
    use \Kompo\Database\HasTranslations;
    protected $translatable = [
        'type',
        'name',
        'subname',
        'description',
    ];

    public const GROUP_ASSETS = 1;
    public const GROUP_LIABILITIES = 2;
    public const GROUP_INCOME = 3;
    public const GROUP_EXPENSE = 4;
    public const GROUP_EQUITY = 5;

    public const CODE_INCOME = '41';
    public const CODE_CASH = '11';
    public const CODE_RECEIVABLES = '12';
    public const CODE_PAYABLES = '22';

    public const CODE_ACOMPTE = '2410';
    public const CODE_WRITEOFF = '2430';
    public const CODE_INTEREST = '4590';
    public const CODE_WAREHOUSE = '4600'; // ! This need rename
    public const CODE_NFS = '4630';
    public const CODE_BANK_FEES = '5700';
    public const CODE_BANK_INTEREST = '4500';

    public const CODE_INTERFUND_ASSET = '1800';
    public const CODE_INTERFUND_LIABILITY = '2700';

    public const CODE_BNR_OPERATING = '3100';
    public const CODE_BNR_CONTINGENCY = '3200';
    public const CODE_BNR_MAINTENANCE = '3300';
    public const CODE_BNR_SPECIAL = '3400';
    public const CODE_BNR_INSURANCE = '3500';

    /* RELATIONSHIPS */
    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }

    public function fund()
    {
        return $this->belongsTo(Fund::class);
    }

    public function tax()
    {
        return $this->belongsTo(Tax::class);
    }

    public function accountBalances()
    {
        return $this->hasMany(AccountBalance::class);
    }

    public function latestBalance($union = null)
    {
        /* TODO RECONNECT
        $union = $union ?: currentUnion();

        if ($latestBalanceDate = $union->latestBalanceDate()) {
            return $this->hasOne(AccountBalance::class)->where('from_date', $latestBalanceDate);
        }*/

        return $this->hasOne(AccountBalance::class);
    }

    public function entries()
    {
        return $this->hasMany(Entry::class);
    }

    public function unbalancedEntries($union = null)
    {
        /* TODO RECONNECT
        $union = $union ?: currentUnion();

        if ($latestBalanceDate = $union->latestBalanceDate()) {
            return $this->entries()->where('transacted_at', '>=', $latestBalanceDate);
        }*/

        return $this->entries();
    }

    public function conciliations()
    {
        return $this->hasMany(Conciliation::class);
    }

    public function conciliation()
    {
        return $this->hasOne(Conciliation::class)->orderByDesc('reconciled_at');
    }

    public function subAccount()
    {
        return $this->hasOne(SubAccount::class);
    }

    /* ATTRIBUTES */
    public function getCategoryAttribute()
    {
        return static::allGroups()[$this->group];
    }

    public function getDisplayShortAttribute()
    {
        return ($this->name ?: $this->type) . ($this->subname ? (' - ' . $this->subname) : '');
    }

    public function getDisplayAttribute()
    {
        $display = $this->type;
        $display .= $this->name ? (' - ' . $this->name) : '';
        $display .= $this->subname ? (' - ' . $this->subname) : '';

        return $display;
    }

    /* CALCULATED FIELDS */
    public static function allGroups()
    {
        return [
            static::GROUP_ASSETS => __('finance-assets'),
            static::GROUP_LIABILITIES => __('finance-liabilities'),
            static::GROUP_INCOME => __('finance-income'),
            static::GROUP_EXPENSE => __('finance-expenses'),
            static::GROUP_EQUITY => __('finance-equity'),
        ];
    }

    public static function bnrCodes()
    {
        return array_merge(
            [
                static::CODE_BNR_OPERATING,
                static::CODE_BNR_CONTINGENCY,
                static::CODE_BNR_MAINTENANCE,
                static::CODE_BNR_SPECIAL,
                static::CODE_BNR_INSURANCE,
            ],
            static::inUnionGl()->whereRaw('LEFT(code,2) = ?', [substr(static::CODE_BNR_SPECIAL, 0, 2)])
                ->where('code', '<>', static::CODE_BNR_SPECIAL)
                ->pluck('code')->toArray()

        );
    }

    public static function getSubGroups($groupId)
    {
        return static::selectRaw('type as type_lang, MAX(LEFT(code,2)) as subcode')
            ->forUnionAll()
            ->where('group', $groupId)
            ->groupByRaw('type_lang')->get();
    }

    public static function getUnionOptions()
    {
        $unionAccounts = static::forTeam()->enabledInGl()->get();

        return collect(static::allGroups())->mapWithKeys(function ($group, $groupKey) use ($unionAccounts) {
            return $unionAccounts->filter(fn ($account) => $account->group == $groupKey)
                ->mapWithKeys(fn ($account) => [
                    $account->id => _Html($account->display)->class('text-greenmain pl-2 -mt-1 -mb-1')
                ])
                ->prepend(
                    _Html($group)
                        ->class('text-gray-600 cursor-not-allowed -mb-1')
                        ->disabled(),
                    '__ignore' . $groupKey, //random generated id
                );
        });
    }

    public static function getLastSibling($code, $teamId = null)
    {
        $category = substr($code, 0, 2); //works with 26 or 2600 for ex.
        $teamId = $teamId ?: currentTeam()->id;

        return static::where('team_id', $teamId)
            ->whereRaw('LEFT(code,2) = ?', [$category])
            ->orderByDesc('code')->first();
    }

    public static function getNextCode($lastSibling)
    {
        $nextCode = (int) $lastSibling->code + 1;

        if (substr($nextCode, 0, 2) != substr($lastSibling->code, 0, 2)) { //another category
            return $lastSibling->code;
        }

        return $nextCode;
    }

    public function getOption()
    {
        return [
            $this->id => $this->getOptionLabel()->searchableBy($this->display)
        ];
    }

    public function getOptionByCode()
    {
        return [
            $this->code => $this->getOptionLabel()->searchableBy($this->display)
        ];
    }

    public function getOptionLabel()
    {
        return _Rows(
            _Html($this->type)->class('text-xs font-semibold text-gray-600'), //do not change text-gray-600 see _Select.scss
            _Html($this->subname ? ($this->subname . ' - ' . $this->name) : ($this->name ?: $this->type))->class('text-sm'),
        );
    }

    public function isDuplicateCode($code)
    {
        return GlAccount::forUnion()->where('code', $code)->where('id', '<>', $this->id)->count() >= 1;
    }

    /* BALANCE CALCULATIONS NEED THE CONCEPT OF CURRENT UNION */
    public function getCurrentBalance() //Own method for efficiency
    {
        $unbalancedCredit = $this->unbalancedEntries->sum('credit');
        $unbalancedDebit = $this->unbalancedEntries->sum('debit');

        return $this->addUnbalanced($unbalancedCredit, $unbalancedDebit);
    }

    public function getBodBalanceFor($date, $union = null)
    {
        return $this->getBalanceFor($date, $union, '<');
    }

    public function getEodBalanceFor($date, $union = null)
    {
        return $this->getBalanceFor($date, $union, '<=');
    }

    public function getBalanceFor($date, $union = null, $operator = '<')
    {
        $union = $union ?: currentUnion();

        if ($date < $union->latestBalanceDate()) {
            return 0;
        }

        $unbalancedCredit = $this->unbalancedEntries($union)->where('transacted_at', $operator, $date)->sum('credit');
        $unbalancedDebit = $this->unbalancedEntries($union)->where('transacted_at', $operator, $date)->sum('debit');

        return $this->addUnbalanced($unbalancedCredit, $unbalancedDebit, $union);
    }

    protected function addUnbalanced($credit, $debit, $union = null)
    {
        $b = $this->latestBalance($union)->first();

        if (static::isDebitor($this->group)) {
            return ($b ? ($b->debit_balance - $b->credit_balance) : 0) - $credit + $debit;
        } else {
            return ($b ? ($b->credit_balance - $b->debit_balance) : 0) + $credit - $debit;
        }
    }

    protected static function getAccountsCreditDebit($accountIds, $atDate, $unionLastBalanceDate = null)
    {
        $latestBalanceDate = $unionLastBalanceDate ?: currentUnion()->latestBalanceDate();

        $atDate = carbon(carbon($atDate)->format('Y-m-d'))->addDays(1)->addSeconds(-1)->format('Y-m-d H:i:s'); //ex: 2022-10-31 23:59:59

        $balance = AccountBalance::where('from_date', $latestBalanceDate)->whereIn('gl_account_id', $accountIds);

        $unbalancedEntries = Entry::notVoid()->whereIn('gl_account_id', $accountIds)->where('transacted_at', '<=', $atDate)
            ->where('transacted_at', '>=', $latestBalanceDate);

        return [
            $balance->sum('credit_balance') + $unbalancedEntries->sum('credit'),
            $balance->sum('debit_balance') + $unbalancedEntries->sum('debit'),
        ];
    }

    protected static function getGroupBalance($groupId = null, $initial = false)
    {
        $qAccounts = static::forTeam()->enabledInGl();
        $qAccounts = $groupId ? $qAccounts->forGroup($groupId) : $qAccounts;

        //$latestBalanceDate = currentUnion()->latestBalanceDate();
        $latestBalanceDate = '2024-01-01'; //Todo change

        $balance = AccountBalance::where('from_date', $latestBalanceDate)->whereIn('gl_account_id', $qAccounts->pluck('id'));

        $unbalancedEntries = Entry::notVoid()->whereIn('gl_account_id', $qAccounts->pluck('id'))
            ->where('transacted_at', '>=', substr($latestBalanceDate, 0, 10));

        return [
            $balance->sum('credit_balance') + ($initial ? 0 : $unbalancedEntries->sum('credit')),
            $balance->sum('debit_balance') + ($initial ? 0 : $unbalancedEntries->sum('debit')),
        ];
    }

    public static function calcCredit($group, $creditAmount, $debitAmount)
    {
        $totalAmount = $debitAmount - $creditAmount;
        if (static::isDebitor($group)) {
            return ($totalAmount < 0) ? -$totalAmount : 0;
        } else {
            $totalAmount = -$totalAmount;
            return ($totalAmount >= 0) ? $totalAmount : 0;
        }
    }

    public static function calcDebit($group, $creditAmount, $debitAmount)
    {
        $totalAmount = $debitAmount - $creditAmount;
        if (static::isDebitor($group)) {
            return ($totalAmount >= 0) ? $totalAmount : 0;
        } else {
            $totalAmount = -$totalAmount;
            return ($totalAmount < 0) ? -$totalAmount : 0;
        }
    }

    public static function isDebitor($group)
    {
        return in_array($group, [static::GROUP_ASSETS, static::GROUP_EXPENSE]);
    }

    public static function isEoyZeroed($group)
    {
        return in_array($group, [static::GROUP_INCOME, static::GROUP_EXPENSE]);
    }

    public function isReceivables()
    {
        return substr($this->code, 0, 2) == static::CODE_RECEIVABLES;
    }

    public function isPayables()
    {
        return substr($this->code, 0, 2) == static::CODE_PAYABLES;
    }

    public function isIncome()
    {
        return substr($this->code, 0, 2) == static::CODE_INCOME;
    }

    public function isAcompteGroup()
    {
        return substr($this->code, 0, 2) == substr(static::CODE_ACOMPTE, 0, 2);
    }

    public function isAcompte()
    {
        return $this->code == static::CODE_ACOMPTE;
    }

    public function isWriteOff()
    {
        return $this->code == static::CODE_WRITEOFF;
    }

    public function isInterest()
    {
        return $this->code == static::CODE_INTEREST;
    }

    public function isBankFees()
    {
        return $this->code == static::CODE_BANK_FEES;
    }

    public function isBankInterest()
    {
        return $this->code == static::CODE_BANK_INTEREST;
    }

    public function isNfs()
    {
        return $this->code == static::CODE_NFS;
    }

    public function isBnr()
    {
        return in_array($this->code, static::bnrCodes());
    }

    public function cannotBeDisabled()
    {
        if ($this->tax_id) {
            return __('finance.cant-disable-tax-account');
        }

        if ($this->isIncome() && $this->union->getFunds()->pluck('id')->contains($this->fund_id)) {
            return __('finance.cant-disable-income-account');
        }

        if (abs($this->getCurrentBalance()) >= 0.01) {
            return __('finance.account-has-non-zero-balance');
        }

        if ($this->isAcompte() || $this->isInterest() || $this->isWriteOff() || $this->isBankFees() || $this->isBankInterest() || $this->isNfs()) {
            return __('finance.account-cant-be-disabled');
        }

        if ($this->isBnr()) {
            return __('finance.surplus-account-cant-be-disabled');
        }

        if (!$this->union->accounts()->receivables()->where('id', '<>', $this->id)->count()) {
            return __('finance.cant-disable-last-receivables-account');
        }

        if (!$this->union->accounts()->payables()->where('id', '<>', $this->id)->count()) {
            return __('finance.cant-disable-last-payables-account');
        }

        if (!$this->union->accounts()->cash()->where('id', '<>', $this->id)->count()) {
            return __('finance.cant-disable-last-cash-account');
        }

        return false;
    }

    /* SCOPES */
    public function scopeForUnionAll($query, $teamId = null)
    {
        return $query->where('team_id', $teamId ?: currentTeamId());
    }

    public function scopeEnabledInGl($query)
    {
        return $query->where('enabled', 1);
    }

    public function scopeInUnionGl($query, $unionId = null)
    {
        return $query->forUnionAll($unionId ?: currentUnionId())->enabledInGl();
    }

    //Account categories scopes
    public function scopeForGroup($query, $group)
    {
        return $query->where('group', $group);
    }

    public function scopeWithCode($query, $code)
    {
        return $query->whereRaw('LEFT(code,2) = ?', [$code]);
    }

    public function scopeForTax($query, $taxId = null)
    {
        if ($taxId) {
            return $query->where('tax_id', $taxId);
        }

        return $query->whereNotNull('tax_id');
    }

    public function scopeCash($query)
    {
        return $query->withCode(static::CODE_CASH);
    }

    public function scopeIncome($query, $fundId = null)
    {
        if ($fundId) {
            $query = $query->where('fund_id', $fundId);
        }

        return $query->withCode(static::CODE_INCOME);
    }

    public function scopeReceivables($query)
    {
        return $query->withCode(static::CODE_RECEIVABLES);
    }

    public function scopePayables($query)
    {
        return $query->withCode(static::CODE_PAYABLES);
    }

    public function scopeAcompte($query)
    {
        return $query->where('code', static::CODE_ACOMPTE);
    }

    public function scopeAcompteGroup($query)
    {
        return $query->withCode(substr(static::CODE_ACOMPTE, 0, 2));
    }

    public function scopeWriteOff($query)
    {
        return $query->where('code', static::CODE_WRITEOFF);
    }

    public function scopeInterest($query)
    {
        return $query->where('code', static::CODE_INTEREST);
    }

    public function scopeBankFees($query)
    {
        return $query->where('code', static::CODE_BANK_FEES);
    }

    public function scopeBankInterest($query)
    {
        return $query->where('code', static::CODE_BANK_INTEREST);
    }

    public function scopeNfs($query)
    {
        return $query->where('code', static::CODE_NFS);
    }

    public function scopeRevenue($query)
    {
        return $query->where('group', static::GROUP_INCOME);
    }

    public function scopeExpense($query)
    {
        return $query->where('group', static::GROUP_EXPENSE);
    }

    public function scopeBnr($query)
    {
        return $query->whereIn('code', static::bnrCodes())->orderBy('code');
    }

    //Account categories scoped by union level
    public function scopeUsableRevenue($query, $unionId = null)
    {
        return $query->inUnionGl($unionId)->revenue();
    }

    public function scopeUsableReceivables($query, $unionId = null)
    {
        return $query->inUnionGl($unionId)->receivables();
    }

    public function scopeUsablePayables($query, $unionId = null)
    {
        return $query->inUnionGl($unionId)->payables();
    }

    public function scopeUsableExpense($query, $unionId = null)
    {
        return $query->inUnionGl($unionId)->expense();
    }

    public function scopeUsableTax($query, $unionId = null, $taxId = null)
    {
        return $query->inUnionGl($unionId)->forTax($taxId);
    }

    public function scopeUsableCash($query, $unionId = null)
    {
        return $query->inUnionGl($unionId)->cash();
    }

    public function scopeUsableAcompte($query, $unionId = null)
    {
        return $query->inUnionGl($unionId)->acompte();
    }

    public function scopeUsableAcompteGroup($query, $unionId = null)
    {
        return $query->inUnionGl($unionId)->acompteGroup();
    }

    public function scopeUsableWriteOff($query, $unionId = null)
    {
        return $query->inUnionGl($unionId)->writeOff();
    }

    /* ACTIONS */
    public function addCreditBalance($balanceDate, $amount)
    {
        $this->addAccountBalance($balanceDate, 'credit_balance', $amount);
    }

    public function addDebitBalance($balanceDate, $amount)
    {
        $this->addAccountBalance($balanceDate, 'debit_balance', $amount);
    }

    public function addZeroBalance($balanceDate)
    {
        $this->addAccountBalance($balanceDate, 'debit_balance', 0);
    }

    public function addAccountBalance($balanceDate, $column, $amount)
    {
        if (!($accountBalance = $this->accountBalances()->where('from_date', $balanceDate)->first())) {
            $accountBalance = new AccountBalance();
            $accountBalance->from_date = $balanceDate;
            $accountBalance->gl_account_id = $this->id;
        }

        $accountBalance->{$column} = $amount ?: 0;
        $accountBalance->save();
    }

    public function delete()
    {
        $this->conciliations()->delete();

        parent::delete();
    }

    /* ELEMENTS */
    public static function groupBalances($initial = false)
    {
        $totalDebit = 0;
        $totalCredit = 0;
        $groupItems = [];

        foreach (GlAccount::allGroups() as $groupId => $group) {
            [$creditAmount, $debitAmount] = static::getGroupBalance($groupId, $initial);

            $debit = static::calcDebit($groupId, $creditAmount, $debitAmount);
            $credit = static::calcCredit($groupId, $creditAmount, $debitAmount);

            $totalDebit += $debit;
            $totalCredit += $credit;

            $groupItems[] = static::balanceRow(
                _Html($group),
                _Currency($debit)->id('total-debit-' . $groupId),
                _Currency($credit)->id('total-credit-' . $groupId),
                static::netColumn($debit, $credit)->id('total-net-' . $groupId),
            );
        }

        return _Rows(
            static::balanceHeader(),
            _Rows(
                ...$groupItems
            ),
            static::balanceRow(
                _Html('Total'),
                _Currency($totalDebit)->id('total-debit-allgroups'),
                _Currency($totalCredit)->id('total-credit-allgroups'),
                static::netColumn($totalDebit, $totalCredit)->id('total-net-allgroups'),
            )->class('font-bold'),
        )->class('bg-gray-50 rounded-2xl p-4');
    }

    public static function balanceRow($label, $debit, $credit, $net)
    {
        return _FlexBetween(
            $label,
            _FlexEnd(
                $net->class('basis-36 shrink-0 font-semibold text-info'),
                $debit->class('basis-28 shrink-0 whitespace-nowrap'),
                $credit->class('basis-28 shrink-0 whitespace-nowrap'),
            )->class('space-x-4 text-right text-sm')
        );
    }

    public static function netColumn($debit, $credit)
    {
        return _FlexEnd(
            _Currency(abs($debit - $credit))->class('basis-28 whitespace-nowrap text-right text-sm')->class('net-ccy'),
            _Html($debit > $credit ? 'dt' : ($debit < $credit ? 'ct' : ''))->class('w-6')->class('net-side'),
        );
    }

    public static function balanceHeader()
    {
        return static::balanceRow(
            _Html('Verification'),
            _Html('Debit'),
            _Html('Credit'),
            _Html('Net')->class('pr-6'),
        )->class('font-bold');
    }

    public static function cashAccountsSelect($relatedToModel = true)
    {
        $cashAccounts = static::inUnionGl()->cash()->with('bank')->get();

        $defaultAccountId = $cashAccounts->filter(fn ($account) => $account->bank?->default_bank)->first()?->id ?:
            $cashAccounts->first()?->id;

        return _Select('Account')->name('gl_account_id', $relatedToModel)
            ->options(
                $cashAccounts
                    ->sortByDesc(fn ($account) => $account->bank?->default_bank)
                    ->mapWithKeys(fn ($account) => $account->getOption())
            )
            ->default($defaultAccountId);
    }

    public static function getToWarehouse($unionId)
    {
        return static::ForUnionAll($unionId)->where(
            'code',
            static::CODE_WAREHOUSE
        )->firstOrFail();
    }
}

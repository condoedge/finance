<?php

namespace Condoedge\Finance\Models;

use App\Models\Finance\GlAccount;
use Kompo\Auth\Models\Model;

class AccountBalance extends Model
{
    use \Condoedge\Finance\Models\BelongsToGlAccountTrait;
    
    /* RELATIONSHIPS */

    /* ATTRIBUTES */
    public function getNetBalanceAttribute()
    {
        $netBalance = $this->debit_balance - $this->credit_balance;

        return GlAccount::isDebitor($this->account->group) ? $netBalance : -$netBalance;
    }

    /* CALCULATED FIELDS */
    public static function getLastLockedDate($teamId = null)
    {
        return static::forTeamGlAccounts($teamId)->orderByDesc('from_date')->value('from_date');
    }

    public static function initialBalancesQuery($date = null, $team = null)
    {
        $team = $team ?: currentTeam();

        return static::where('from_date', $date)
            ->whereIn('gl_account_id', GlAccount::inTeamGl($team)->pluck('id'));
    }

    public static function getBalancesTable($date = null) //This one doesnt merge debit and credit, but keeps them separate.
    {
        $date = $date ?: (currentUnion()->balance_date ?: date('Y-m-d'));

        $totalDebit = 0;
        $totalCredit = 0;
        $groupItems = [];

        foreach (GlAccount::allGroups() as $groupId => $group) {
            [$creditAmount, $debitAmount] = static::getInitialBalance($date, $groupId);

            $totalDebit += $debitAmount;
            $totalCredit += $creditAmount;

            $groupItems[] = GlAccount::balanceRow(
                _Html($group),
                _Currency($debitAmount)->id('total-debit-'.$groupId),
                _Currency($creditAmount)->id('total-credit-'.$groupId),
                GlAccount::netColumn($debitAmount, $creditAmount)->id('total-net-'.$groupId),
            );
        }

        return _Rows(
            GlAccount::balanceHeader(),
            _Rows(
                ...$groupItems
            ),
            GlAccount::balanceRow(
                _Html('Total'),
                _Currency($totalDebit)->id('total-debit-allgroups'),
                _Currency($totalCredit)->id('total-credit-allgroups'),
                GlAccount::netColumn($totalDebit, $totalCredit)->id('total-net-allgroups'),
            )->class('font-bold'),
        )->class('bg-gray-50 rounded-2xl p-4');
    }

    protected static function getInitialBalance($date = null, $groupId = null)
    {
        $date = $date ?: currentTeam()->balance_date;

        $qAccounts = GlAccount::inTeamGl();
        $qAccounts = $groupId ? $qAccounts->forGroup($groupId) : $qAccounts;

        $balance = AccountBalance::where('from_date', $date)->whereIn('gl_account_id', $qAccounts->pluck('id'));

        return [
            $balance->sum('credit_balance'),
            $balance->sum('debit_balance'),
        ];
    }

    /* SCOPES */
}

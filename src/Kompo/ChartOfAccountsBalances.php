<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Models\GlAccount;
use Condoedge\Finance\Models\AccountBalance;
use App\View\Form;

class ChartOfAccountsBalances extends Form
{
    public $class = 'card-white overflow-x-auto';

    public $id = 'chart-of-accounts-balances';

    public function authorizeBoot()
    {
        return AccountBalance::with('account')
            ->whereIn('account_id', GlAccount::inUnionGl()->pluck('id'))
            ->select('from_date')->distinct('from_date')
            ->count() <= 1;
    }

    public function render()
    {
        return [
            _CardHeader('finance.setup-initial-balances')->class('mb-4'),
            _Rows(
                //_Columns(
                    _Rows(
                        _Html('finance.if-you-have-past-balances')
                            ->class('text-sm mb-4'),
                        new AccountBalanceStartDate(currentUnion()->id),
                    ),
                    AccountBalance::getBalancesTable()
                //)
            )->class('px-4 mb-4'),
            _Rows(
                collect(GlAccount::allGroups())->map(
                    fn($group, $groupId) =>
                    _Rows(
                        _FlexBetween(
                            _Link($group)->class('text-white')
                                ->icon('icon-down')->id('group-toggle'.$groupId)
                                ->run('() => { toggleGroup('.$groupId.') }'),
                            _FlexEnd4(
                                _Html('Debit')->class('w-28 pr-4'),
                                _Html('Credit')->class('w-28 pr-4'),
                            )->class('text-right'),
                        )->class('text-xl font-bold bg-level2 text-white mb-2 px-4 py-3 rounded-xl'),
                        _Rows(
                            GlAccount::getSubGroups($groupId)->map(
                                fn($subgroup) => new AccountsBalancesList([
                                    'account_type' => $subgroup->type_lang,
                                    'sub_code_id' => $subgroup->subcode,
                                    'group_id' => $groupId,
                                ])
                            ),
                        )->id('group-block'.$groupId)->class('hidden'),
                    )->class('mb-6 group-balances')
                    ->attr(['data-group' => $groupId])
                ),
            )->class('px-4'),
        ];
    }

    /* For when it is opened as a standalone page from settings */
    public function js()
    {
        return file_get_contents(resource_path('views/scripts/finance.js'));
    }
}

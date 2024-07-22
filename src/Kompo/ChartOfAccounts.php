<?php

namespace Condoedge\Finance\Kompo;

use App\Models\Finance\GlAccount;
use Kompo\Form;

class ChartOfAccounts extends Form
{
    public $class = 'max-w-4xl mx-auto';

    protected $groupId;
    protected $allAccounts;

    protected $coaPanelId = 'chart-balance-verification';

    public function created()
    {
        $team = currentTeam();

        GlAccount::createInitialAccountsIfNone($team);

        $this->groupId = $this->prop('group') ?: 1;
        $this->allAccounts = $this->prop('all');
    }

    public function render()
    {
        return [
            _FlexBetween(
                _TitleMain('finance-chart-of-accounts')->class('mb-4'),
                _FlexEnd(
                    _Link(
                        '<span class="hidden sm:inline">'.
                            ($this->allAccounts ? __('finance-show-active-accounts') : __('finance-show-inactive-accounts')).
                        '</span>'
                    )->icon('ban')->class('text-sm')
                    ->href('finance.chart-of-accounts', [
                        'group' => $this->groupId,
                        'all' => $this->allAccounts ? 0 : 1,
                    ]),
                    _Button('finance-balance-verification')->outlined()
                        ->icon('view-list')
                        ->selfGet('getBalanceVerificationBox')->inPanel($this->coaPanelId),
                )->class('space-x-4 mb-4')
            )->class('flex-wrap'),
            _Panel()->id($this->coaPanelId),
            _Flex(
                collect(GlAccount::allGroups())->map(
                    fn($group, $groupId) => _TabLink($group, $this->groupId == $groupId)
                                                ->href('finance.chart-of-accounts', [
                                                    'group' => $groupId,
                                                    'all' => $this->allAccounts,
                                                ])
                ),
            ),
            _Rows(
                GlAccount::getSubGroups($this->groupId)->map(
                    fn($subgroup) => new AccountsList([
                        'account_type' => $subgroup->type_lang,
                        'sub_code_id' => $subgroup->subcode,
                        'group_id' => $this->groupId,
                        'all_accounts' => $this->allAccounts,
                    ])
                )
            )->class('space-y-4 my-4'),
        ];
    }

    public function getBalanceVerificationBox()
    {
        return GlAccount::groupBalances()->class('mb-4');
    }
}

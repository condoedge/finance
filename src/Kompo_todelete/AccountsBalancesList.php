<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Models\GlAccount;
use App\View\Query;

class AccountsBalancesList extends Query
{
    public $class = 'accounts-balances-list mb-4';

    public $perPage = 100;

    protected $subCodeId;
    protected $accountType;

    protected $mainAccount;

    protected $totalDebit = 0;
    protected $totalCredit = 0;

    public function created()
    {
        $this->subCodeId = $this->prop('sub_code_id');
        $this->accountType = $this->prop('account_type');

        $this->itemsWrapperClass = 'hidden subgroup-block'.$this->subCodeId;
    }

    public function query()
    {
        return GlAccount::forTeam()->with('accountBalances', 'unbalancedEntries')
            ->where('type', $this->accountType);
    }

    public function topOnLoad()
    {
        $this->mainAccount = GlAccount::find($this->query->first()['attributes']['id']);

        return _FlexBetween(
            _Flex2(
                _Link($this->mainAccount->type)->class('font-bold text-level2 text-sm')
                    ->icon('icon-down')->id('subgroup-toggle'.$this->subCodeId)
                    ->run('() => { toggleSubGroup('.$this->subCodeId.') }'),
                _AddLink('finance.add-new-account')->class('text-sm text-level2 hidden subgroup-add'.$this->subCodeId)
                    ->get('account.form', [
                        'sub_code_id' => $this->subCodeId,
                    ]),
            ),
            _FlexEnd4(
                _Currency($this->totalDebit)->class('w-28 balance-debit-subcode'),
                _Currency($this->totalCredit)->class('w-28 balance-credit-subcode'),
            )->class('text-right font-bold pr-4'),
        )->class('p-4 bg-level3 rounded-xl');
    }

    public function render($account, $key)
    {
        $configurationLink = null;

        if ($key == 0) {
            if ($account->isReceivables()) {
                $configurationLink = $this->receivablesLink();
            }

            if ($account->isPayables()) {
                $configurationLink = $this->payablesLink();
            }

            if ($account->isAcompteGroup()) {
                //$configurationLink = $this->paymentsLink();
            }

            $configurationLink = _FlexEnd($configurationLink)->class('border-b border-gray-100 -mt-2 pb-2');
        }

        $accountBalance = $account->accountBalances->where('from_date', currentUnion()->balance_date ?: date('Y-m-d'))->first();

        $this->totalDebit += $accountBalance?->debit_balance ?: 0;
        $this->totalCredit += $accountBalance?->credit_balance ?: 0;

    	return _Rows(
            _FlexBetween(
                _Flex4(
                    _Html($account->code)
                        ->class('w-12 text-gray-600'),
                    _Html($account->display_short)
                        ->class($account->enabled ? '' : 'line-through text-gray-600'),
                )->selfUpdate('getAccountForm', [
                    'id' => $account->id,
                ])->inModal(),
                _FlexEnd4(
                    _Flex(
                        !$account->bank_id ? null :
                            _Link($account->display)->icon(_Sax('bank',20))
                                ->class('px-3 py-1 border border-info rounded-lg')
                                ->selfUpdate('getBankForm', ['id' => $account->bank_id])->inModal(),
                        AccountsList::toggleAccountKomponent($account),
                    )->class('hidden group-hover:flex space-x-4'),
                    _InputNumber()->name('debit')->placeholder('finance.debit')
                        ->inputClass('debit-balance input-number-no-arrows text-right')->class('w-28 shrink-0 mb-0')
                        ->value($accountBalance?->debit_balance ?: '0.00')->debounce(1000)
                        ->selfPost('addDebitBalance', [
                            'account_id' => $account->id,
                        ])->run('calculateTotalBalances'),
                    _InputNumber()->name('credit')->placeholder('finance.credit')
                        ->inputClass('credit-balance input-number-no-arrows text-right')->class('w-28 shrink-0 mb-0')
                        ->value($accountBalance?->credit_balance ?: '0.00')->debounce(1000)
                        ->selfPost('addCreditBalance', [
                            'account_id' => $account->id,
                        ])->run('calculateTotalBalances'),
                )->class('text-right text-sm'),
            )->class('py-2 px-4 space-x-4 bg-white hover:bg-gray-50 group')
            ->class($configurationLink ? '' : 'border-b border-gray-100')
            ->class('flex-wrap md:flex-nowrap'),
            $configurationLink,
    	);
    }

    public function getAccountForm($id)
    {
        return new AccountForm($id, [
            'sub_code_id' => $this->subCodeId,
        ]);
    }

    public function getBankForm($id)
    {
        return new BankForm($id);
    }

    public function toggleEnabled()
    {
        return AccountsList::performAccountToggle(request('account_id'));
    }

    public function addDebitBalance($accountId, $amount)
    {
        GlAccount::findOrFail($accountId)->addDebitBalance(currentUnion()->balance_date, $amount);
    }

    public function addCreditBalance($accountId, $amount)
    {
        GlAccount::findOrFail($accountId)->addCreditBalance(currentUnion()->balance_date, $amount);
    }

    public function configureReceivables()
    {
        return new AccountsReceivablesInitialAmountsForm();
    }

    public function configurePayables()
    {
        return new AccountsPayablesInitialAmountsForm();
    }

    protected function receivablesLink()
    {
        return $this->configurationLink('finance.configure-past-receivables-due')
            ->selfGet('configureReceivables')->inDrawer();
    }

    protected function payablesLink()
    {
        return $this->configurationLink('finance.configure-past-payables-due')
            ->selfGet('configurePayables')->inDrawer();
    }

    protected function paymentsLink()
    {
        return $this->configurationLink('finance.configure-past-payments-due')
            ->selfGet('configureReceivables')->inDrawer();
    }

    protected function configurationLink($label)
    {
        return _Button($label)->icon('arrow-right')->class('mt-2');
    }
}

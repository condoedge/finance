<?php

namespace Condoedge\Finance\Kompo;

use App\Models\Finance\GlAccount;
use Kompo\Query;

class AccountsList extends Query
{
    public $perPage = 100;

    protected $subCodeId;
    protected $accountType;

    protected $mainAccount;
    protected $allAccounts;

    public function noItemsFound()
    {
        return;
    }

    public function created()
    {
        $this->subCodeId = $this->prop('sub_code_id');
        $this->accountType = $this->prop('account_type');

        $this->allAccounts = $this->store('all_accounts');

        $this->mainAccount = $this->query()->first();

        $this->itemsWrapperClass = 'subgroup-block'.$this->subCodeId;
    }

    public function query()
    {
        $query = GlAccount::forTeam();
        
        if ($this->allAccounts) {
            $query = $query->enabledInGl();
        }

        return $query->with('bank')->where('type', $this->accountType);
    }

    public function top()
    {
        if (!$this->mainAccount) {
            return;
        }

        return _FlexBetween(
            _Link($this->mainAccount->type)->class('font-bold text-greenmain text-sm my-2')
                ->icon('icon-up')->id('subgroup-toggle'.$this->subCodeId)
                ->run('() => { toggleSubGroup('.$this->subCodeId.') }'),
            _AddLink('finance-add-new-account')->class('text-sm mt-1 text-level2 subgroup-add'.$this->subCodeId)
                ->selfGet('getAccountForm', [
                    'sub_code_id' => $this->subCodeId,
                ])->inModal()
        )->class('px-4 bg-level5 text-level1 rounded-t-2xl py-2');
    }

    public function render($account)
    {
    	return _FlexBetween(
            _Rows(
                _Flex4(
                    _Html($account->code)->class('w-14 text-gray-600'),
                    _EditLink($account->display_short)
                        ->class($account->enabled ? '' : 'line-through text-gray-600')
                        ->selfGet('getAccountForm', [
                            'sub_code_id' => $this->subCodeId,
                            'id' => $account->id,
                        ])->inModal(),
                ),
                $account->isAcompte() ? $this->acomptesLink($account) : null,
            ),
            _Flex4(
                _Flex(
                    !$account->bank_id ? null :
                        _Link($account->bank->display)->icon(_Sax('bank',20))
                            ->class('text-sm px-3 py-1 border border-info rounded-xl')
                            ->selfUpdate('getBankForm', ['id' => $account->bank_id])->inModal(),
                    static::toggleAccountKomponent($account),
                )->class('hidden group-hover:flex space-x-4'),
                _Currency($account->getCurrentBalance())
                    ->class('text-level1')
            ),
    	)->class('py-2 px-4 space-x-4 bg-white border-b border-gray-100 hover:bg-gray-50 group');
    }

    public function getBankForm($id)
    {
        return new BankForm($id);
    }

    public function getAccountForm()
    {
        return new AccountForm(request('id'), [
            'sub_code_id' => request('sub_code_id'),
        ]);
    }

    public function toggleEnabled()
    {
        return static::performAccountToggle(request('account_id'));
    }

    public static function performAccountToggle($accountId)
    {
        $account = GlAccount::findOrFail(request('account_id'));

        if ($message = $account->cannotBeDisabled()) {
            abort(403, $message);
        }

        $account->enabled = request('enabled') ? 1 : 0;
        $account->save();
    }

    public static function toggleAccountKomponent($account)
    {
        return _Toggle('finance.enabled')->name('enabled')->value($account->enabled)->class('mb-0')
            ->selfPost('toggleEnabled', [
                'account_id' => $account->id,
            ])->onSuccess(fn($e) => $e->browse())
            ->onError(fn($e) => $e->inAlert('icon-times', 'vlAlertError')->browse());
    }

    public function showAcomptes()
    {
        return new AcomptesAmountsPerUnitsModal();
    }

    protected function acomptesLink()
    {
        return $this->configurationLink('finance-view-advance-payments-per-client')
            ->selfGet('showAcomptes')->inModal();
    }

    protected function configurationLink($label)
    {
        return _Link($label)->icon('arrow-right')->class('mt-2 text-sm font-semibold text-gray-600 underline');
    }
}

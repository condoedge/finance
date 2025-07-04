<?php

namespace Condoedge\Finance\Kompo\ChartOfAccounts;

use Condoedge\Finance\Models\SegmentValue;
use Kompo\Query;

class AccountsList extends Query
{
    public $perPage = 100;
    protected $accountType;

    public function created()
    {
        $this->accountType = $this->prop('account_type');
    }

    public function query()
    {
        return SegmentValue::query()
            ->when($this->accountType && $this->accountType != 'all', fn ($q) => $q->where('account_type', $this->accountType))
            ->forLastSegment();
    }

    public function render($account)
    {
        return _FlexBetween(
            _FlexBetween(
                _Html($account->segment_value),
                _Html($account->segment_description)->class('w-[100px] text-left'),
            )->class('w-[300px]'),
            _Flex(
                _Toggle()->name('is_active', false)->default($account->is_active)->class('!mb-0')
                    ->selfPost('toggleAccount', [
                        'account_id' => $account->id,
                    ])->alert('translated.toggled-successfully'),
                _Delete($account),
            )->class('gap-4'),
        )->class('py-2 px-4 space-x-4 bg-white rounded-lg mb-2 border border-gray-200 !items-center');
    }

    public function toggleAccount()
    {
        $account = SegmentValue::findOrFail(request('account_id'));
        $account->is_active = !$account->is_active;
        $account->save();
    }
}

<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Models\GlAccount;
use Condoedge\Finance\Models\Conciliation;
use Kompo\Table;

class BankReconciliationsTable extends Table
{
    public function query()
    {
        return Conciliation::whereIn('account_id', GlAccount::inUnionGl()->pluck('id'))->orderByDesc('created_at');
    }

    public function top()
    {
        return _Rows(
            _FlexBetween(
                _PageTitle('finance.reconciliations'),
                _FlexEnd4(
                    _Button('finance.new-reconciliation')->icon(_Sax('add',20))->selfGet('getNewReconciliationForm')->inModal(),
                ),
            )->class('mb-4'),
        );
    }

    public function headers()
    {
        return [
            _Th('finance.reconcilied'),
            _Th('Account'),
            _Th('finance.opening-balance')->class('text-right'),
            _Th('finance.closing-balance')->class('text-right'),
            _Th('finance.amount-reconcilied')->class('text-right'),
            _Th('finance.amount-remaining')->class('text-right'),
            _Th(),
        ];
    }

    public function render($conciliation)
    {
    	return _TableRow(
            _Html(carbon($conciliation->start_date)->translatedFormat('F Y'))->class('whitespace-nowrap'),
            _Html($conciliation->account->display),
            _Currency($conciliation->opening_balance)->class('text-right'),
            _Currency($conciliation->closing_balance)->class('text-right'),
            _Currency($conciliation->resolved)->class('text-right'),
            _Currency($conciliation->remaining)->class('text-right'),
            _DeleteLink()->byKey($conciliation),
        )->href('conciliation.page', [
            'id' => $conciliation->id,
        ])->class('cursor-pointer');
    }

    public function getNewReconciliationForm()
    {
        return new BankReconciliationNewForm();
    }
}

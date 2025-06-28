<?php

namespace Condoedge\Finance\Kompo\GlTransactions;

use Condoedge\Finance\Models\GlTransactionHeader;
use Kompo\Table;

class GlTransactionsTable extends Table
{
    public function query()
    {
        return GlTransactionHeader::forTeam()
            ->with(['glTransactionLines', 'createdBy'])
            ->orderByDesc('gl_transaction_number');
    }
    
    public function top()
    {
        return _FlexBetween(
            _TitleMain('finance-gl-transactions'),
            _Link('finance-create-gl-transaction')
                ->button()
                ->outlined()
                ->icon('plus')
                ->href('finance.gl.gl-transaction-form')
        );
    }
    
    public function headers()
    {
        return [
            _Th('finance-transaction-number')->sort('gl_transaction_number'),
            _Th('finance-transaction-date')->sort('gl_transaction_date'),
            _Th('finance-type'),
            _Th('finance-description'),
            _Th('finance-total-amount'),
            _Th('finance-status'),
            _Th(''),
        ];
    }
    
    public function render($transaction)
    {
        return _TableRow(
            _Html($transaction->gl_transaction_number)->class('font-mono'),
            _Date($transaction->gl_transaction_date),
            _Html($transaction->getTypeLabel()),
            _Html($transaction->gl_transaction_description),
            _Currency($transaction->getTotalDebits()),
            _Pill($transaction->is_posted ? __('finance-posted') : __('finance-draft'))
                ->class($transaction->is_posted ? 'bg-success text-white' : 'bg-gray-200'),
            _Dropdown(
                _Link('finance-view')->icon('eye')
                    ->href('finance.gl.gl-transaction-form', ['id' => $transaction->id]),
                !$transaction->is_posted ? 
                    _Link('finance-edit')->icon('pencil')
                        ->href('finance.gl.gl-transaction-form', ['id' => $transaction->id]) : null,
                !$transaction->is_posted ?
                    _Link('finance-post')->icon('check')
                        ->selfPost('postTransaction', ['id' => $transaction->id])->refresh() : null,
            )->alignRight()
        );
    }
    
    public function postTransaction($id)
    {
        $transaction = GlTransactionHeader::findOrFail($id);
        
        try {
            app(\Condoedge\Finance\Services\GlTransactionService::class)->postTransaction($transaction);
            $this->notifySuccess(__('finance-transaction-posted-successfully'));
        } catch (\Exception $e) {
            $this->notifyError($e->getMessage());
        }
    }
}

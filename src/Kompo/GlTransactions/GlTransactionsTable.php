<?php

namespace Condoedge\Finance\Kompo\GlTransactions;

use Condoedge\Finance\Models\GlTransactionHeader;

use Condoedge\Finance\Services\GlTransactionServiceInterface;
use Condoedge\Utils\Kompo\Common\Table;

class GlTransactionsTable extends Table
{
    protected $teamId;
    
    public function created()
    {
        $this->teamId = currentTeamId();
    }
    
    public function query()
    {
        return GlTransactionHeader::with(['lines', 'fiscalPeriod', 'customer'])
            ->forTeam($this->teamId)
            ->orderBy('gl_transaction_number');
    }
    
    public function top()
    {
        return _FlexBetween(
            _TitleMain('translate.finance-gl-transactions'),
            _Link('translate.finance-create-transaction')->button()
                ->icon('plus')
                ->href(route('finance.gl.gl-transaction-form'))
                ->class('btn-primary')
        );
    }
    
    public function headers()
    {
        return [
            _Th('translate.finance-transaction-id')->sort('gl_transaction_id'),
            _Th('translate.finance-date')->sort('fiscal_date'),
            _Th('translate.finance-description'),
            _Th('translate.finance-type'),
            _Th('translate.finance-debits')->class('text-right'),
            _Th('translate.finance-credits')->class('text-right'),
            _Th('translate.finance-status')->class('text-center'),
            _Th('translate.finance-actions')->class('text-center'),
        ];
    }
    
    public function render($transaction)
    {
        return _TableRow(
            // Transaction ID
            _Html($transaction->id)
                ->class('font-mono text-sm'),
            
            // Fiscal Date
            _Html($transaction->fiscal_date->format('Y-m-d')),
            
            // Description
            _Html($transaction->transaction_description)
                ->class('max-w-xs truncate'),
            
            // Type
            $transaction->transactionTypePill(),
            
            // Total Debits
            _FinanceCurrency($transaction->total_debits)
                ->class('text-right font-mono'),
            
            // Total Credits
            _FinanceCurrency($transaction->total_credits)
                ->class('text-right font-mono'),
            
            // Status
            $this->renderStatus($transaction),
            
            // Actions
            $this->renderActions($transaction)
        );
    }
    
    /**
     * Render transaction status
     */
    protected function renderStatus($transaction)
    {
        return _Flex(
            // Balance status
            $transaction->is_balanced ?
                _Html()->icon('check-circle')->class('text-success') :
                _Html('ERROR')->class('text-danger'),
            
            // Posted status
            $transaction->is_posted ?
                _Pill('translate.finance-posted')->class('bg-positive text-white') :
                _Pill('translate.finance-draft')->class('bg-gray-200')
        )->class('gap-2 justify-center');
    }
    
    /**
     * Render action buttons
     */
    protected function renderActions($transaction)
    {
        return _Flex(
            // View/Edit
            _Link()
                ->icon($transaction->is_posted ? 'eye' : 'pencil')
                ->href(route('finance.gl.gl-transaction-form', $transaction->gl_transaction_id))
                ->class('text-primary'),
            
            // Post button (if not posted and balanced)
            !$transaction->is_posted && $transaction->is_balanced ?
                _Link()
                    ->icon('check')
                    ->selfPost('postTransaction', ['transaction_id' => $transaction->gl_transaction_id])
                    ->inAlert('translate.finance-confirm-post-transaction')
                    ->class('text-success') : null,
            
            // Reverse button (if posted)
            $transaction->is_posted ?
                _Link()
                    ->icon('rotate-ccw')
                    ->selfGet('getReverseModal', ['transaction_id' => $transaction->gl_transaction_id])
                    ->inModal()
                    ->class('text-warning') : null,
            
            // Delete button (if not posted)
            !$transaction->is_posted ?
                _DeleteLink()
                    ->byKey($transaction)
                    ->class('text-danger') : null
        )->class('gap-2 justify-center');
    }
    
    /**
     * Post a transaction
     */
    public function postTransaction(GlTransactionServiceInterface $glTransactionService)
    {
        try {
            $transactionId = request('transaction_id');
            $transaction = GlTransactionHeader::where('gl_transaction_id', $transactionId)
                ->forTeam($this->teamId)
                ->firstOrFail();
            
            $glTransactionService->postTransaction($transaction);
            
            return _Alert('translate.finance-transaction-posted-successfully')
                ->icon('check')
                ->success()
                ->refresh();
                
        } catch (\Exception $e) {
            return _Alert($e->getMessage())->error();
        }
    }
    
    /**
     * Get reverse transaction modal
     */
    public function getReverseModal()
    {
        //TODO
    }
}

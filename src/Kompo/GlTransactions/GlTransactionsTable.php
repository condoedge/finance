<?php

namespace Condoedge\Finance\Kompo\GlTransactions;

use Condoedge\Finance\Models\GlTransactionHeader;

use Condoedge\Finance\Services\GlTransactionServiceInterface;
use Condoedge\Utils\Kompo\Common\WhiteTable;

class GlTransactionsTable extends WhiteTable
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
            _TitleMain('finance-gl-transactions'),
            _Link('finance-create-transaction')->button()
                ->icon('plus')
                ->href('finance.gl.gl-transaction-form'),
        )->class('mb-4');
    }
    
    public function headers()
    {
        return [
            _Th('finance-transaction-id')->sort('gl_transaction_id'),
            _Th('finance-date')->sort('fiscal_date'),
            _Th('finance-description'),
            _Th('finance-type'),
            _Th('finance-debits')->class('text-right'),
            _Th('finance-credits')->class('text-right'),
            _Th('finance-status')->class('text-center'),
            _Th('finance-actions')->class('text-center'),
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
                _Pill('finance-posted')->class('bg-positive text-white') :
                _Pill('finance-draft')->class('bg-gray-200')
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
                ->href('finance.gl.gl-transaction-form', $transaction->id),
            
            // Post button (if not posted and balanced)
            !$transaction->is_posted && $transaction->is_balanced ?
                _Link()
                    ->icon('check')
                    ->selfPost('postTransaction', ['transaction_id' => $transaction->id])->browse()
                        : null,
            
            // Reverse button (if posted)
            $transaction->is_posted ?
                _Link()
                    ->icon('rotate-ccw')
                    ->selfGet('getReverseModal', ['transaction_id' => $transaction->id])
                    ->inModal() : null,
            
            // Delete button (if not posted)
            !$transaction->is_posted ?
                _Delete($transaction) : null
        )->class('gap-2 justify-center');
    }
    
    /**
     * Post a transaction
     */
    public function postTransaction(GlTransactionServiceInterface $glTransactionService)
    {
        $transactionId = request('transaction_id');
        $transaction = GlTransactionHeader::where('id', $transactionId)
            ->forTeam($this->teamId)
            ->firstOrFail();
        
        $glTransactionService->postTransaction($transaction);
    }
    
    /**
     * Get reverse transaction modal
     */
    public function getReverseModal()
    {
        //TODO
    }
}

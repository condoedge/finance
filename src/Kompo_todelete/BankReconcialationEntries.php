<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Models\Conciliation;
use Condoedge\Finance\Models\Entry;
use App\View\Reports\BaseExportableReport;

class BankReconcialationEntries extends BaseExportableReport
{
    public $layout = 'Table';

    protected $conciliationId;
    protected $conciliation;
    protected $startDate;
    protected $endDate;

    protected $balanceAmount;

    public $noItemsFound = 'finance.no-transactions-in-account';

    public $class = 'w-full';

    public $perPage = 1000;

    public function created()
    {
        parent::created();

        $this->conciliationId = $this->prop('conciliation_id');
        $this->conciliation = Conciliation::findOrFail($this->conciliationId);

        $this->balanceAmount = $this->conciliation->account->getBodBalanceFor($this->conciliation->start_date);

        $this->reportTitle = __('finance.reconciliation').' '.$this->conciliation->account->display;
    }

    public function query()
    {
        return Entry::notVoid()
            ->where('account_id', $this->conciliation->account_id)
            ->where(
                fn($q) => $q->whereBetween('transacted_at', [$this->conciliation->start_date, $this->conciliation->end_date])
                    ->orWhere(
                        fn($q) => $q->where('transacted_at', '<', $this->conciliation->start_date)
                            ->where(
                                fn($q) => $q
                                    ->where('reconciled_during', $this->conciliation->end_date)
                                    ->orWhereNull('reconciled_during')
                            )
                    )
            )
            ->orderBy('transacted_at')
            ->with('transaction');
    }

    public function top()
    {
        return _Rows(
            //_Html($this->reportTitle)->class('text-xl font-bold text-level3 text-opacity-50 mr-4 mb-4'),
            _FlexBetween(
                _Flex4(
                    _Button('finance.interest-revenue')->icon(_Sax('dollar-circle'))
                        ->selfCreate('getBankInterestModal')->inModal(),
                    _Button('finance.bank-fees')->icon(_Sax('dollar-circle'))
                        ->selfCreate('getBankFeesModal')->inModal(),
                ),
                _ExportButtons()
            )->class('mb-4'),
        );
    }

    public function headers()
    {
        return [
            _Th('Date')->class('w-32')->sort('transacted_at'),
            _Th('Description')->sort('description'),
            _Th('finance.payment-num')->class('w-32')->sort('payment_number'),
            _Th('finance.debit')->class('text-right w-32')->sort('debit'),
            _Th('finance.credit')->class('text-right w-32')->sort('credit'),
            //_Th('finance.balance')->class('text-right w-32'),
            _FlexEnd4(
                _CheckAllItems()->run('checkReconciliationAmount')
                    ->name('entry_check_all')
                    ->selfPost('saveAllEntryIds')
            )->class('mr-3')->balloon('condo.check-uncheck-all','left'),
        ];
    }

    public function render($entry)
    {
        //$this->balanceAmount += ($entry->debit - $entry->credit);

        $tableRow = _TableRow(
            _Html($entry->transacted_at->format('d M Y')),
            _Html($entry->description),
            _Html($entry->payment_number),
            _Currency($entry->debit ?: 0)->class('text-right')->class('recon-debit'),
            _Currency($entry->credit ?: 0)->class('text-right')->class('recon-credit'),
            //_Currency($this->balanceAmount ?: 0)->class('text-right'),
            _FlexEnd(
                _Checkbox()->name('entry_check')->class('mb-0 recon-check child-checkbox')
                    ->selfPost('saveEntryId', ['entry_id' => $entry->id])
                    ->run('checkReconciliationAmount')
                    ->value(in_array($entry->id, explode(',', $this->conciliation->reconciled_ids ?: ''))),
            ),
        )->class('recon-row');

        if ($entry->transaction->isBankTransaction()) {
            $tableRow = $tableRow->href('transactions.form', ['id' => $entry->transaction_id]);
        }

        return $tableRow;
    }

    public function saveEntryId($entryId)
    {
        $this->conciliation->syncEntryToReconciled(request('entry_id'), request('entry_check'));
    }

    public function saveAllEntryIds()
    {
        $this->conciliation->reconciled_ids = request('entry_check_all') ? $this->query()->pluck('id')->implode(',') : null;
        $this->conciliation->save();
        $this->conciliation->calculateAmountsFromEntries();

        $this->query()->update([
            'reconciled_during' => request('entry_check_all') ? $this->conciliation->end_date : null,
        ]);
    }

    public function getBankInterestModal()
    {
        return new BankInterestModal([
            'conciliation_id' => $this->conciliationId,
        ]);
    }

    public function getBankFeesModal()
    {
        return new BankFeesModal([
            'conciliation_id' => $this->conciliationId,
        ]);
    }
}

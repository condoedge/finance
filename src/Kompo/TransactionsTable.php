<?php

namespace Condoedge\Finance\Kompo;

use App\Models\Finance\GlAccount;
use App\Models\Finance\Entry;
use App\Models\Finance\Transaction;
use Kompo\Table;

class TransactionsTable extends Table
{
    public $itemsWrapperClass = 'overflow-y-auto mini-scroll bg-white rounded-2xl p-4';
	public $containerClass = 'container-fluid';

    public $perPage = 50;

    protected $alwaysShowVoid = false;

    protected $selectedAccountId;

    public function created()
    {
        $this->selectedAccountId = request('account_id');
    }

    public function query()
    {
        $query = Transaction::where('team_id', currentTeamId());

        if ($this->selectedAccountId) {
            $query = $query->whereHas('entries', fn($q) => $q->where('account_id', $this->selectedAccountId));
        }

        if (!request('show_void') && !$this->alwaysShowVoid) {
            $query = $query->notVoid();
        }

        if($year = request('year')) {
            $query = $query->whereRaw('YEAR(transacted_at) = ?', [$year]);
        }

        if($yearMonth = request('month')) {
            $query = $query->whereRaw('LEFT(transacted_at, 7) = ?', [$yearMonth]);
        }

        return $query->with('mainEntry')->orderByDesc('transacted_at')->orderByDesc('id');
    }

    public function top()
    {
        return _Rows(
            _FlexBetween(
                _TitleMain('Transactions'),
                _FlexEnd4(
                    _Link('finance-add-entry')->button()
                        ->href('finance.transaction-form'),
                )->class('space-x-2')
            )->class('mb-4'),
            _Columns(
                _Select()->name('account_id', false)
                    ->placeholder('finance-filter-by-account')
                    ->options(
                        GlAccount::getUnionOptions()
                    )->default($this->selectedAccountId)
                    ->filter(),
                _Select()->name('type')
                    ->placeholder('finance-filter-by-type')
                    ->options(
                        Transaction::types()
                    )->filter(),
                _Toggle('finance-show-void-too')->name('show_void', false)->class('mt-2')->filter(),
            ),
            _Panel(
                $this->yearMonthLinkGroup()
            )->id('transactions-year-month-filter')
        );
    }

    public function headers()
    {
        return [
            _Th('#')->class('w-1/12'),
            _Th('finance.date')->sort('transacted_at')->class('w-1/12'),
            _Th('finance.type')->sort('type')->class('w-1/12'),
            _Th('finance.method')->class('w-1/6'),
            //_Th('Account'),
            _Th('finance.description'),
            _Th('finance.amount')->class('text-right')->class('w-1/12'),
            _Th()->class('w-1/12'),
        ];
    }

    public function render($transaction)
    {
        $pmtNumber = $transaction->getPaymentNumber();

    	return _TableRow(
            _Html($transaction->id)->class('text-gray-600 text-xs'),
            _Html($transaction->transacted_at)->class('whitespace-nowrap'),
            $transaction->txTypePill(),
            _Html($transaction->isReversed() ? 'void' : $transaction->mainPaymentMethod())
                ->class('text-xs px-3 py-1 rounded-lg inline-block')
                ->class($transaction->isReversed() ? 'bg-danger text-level1' : 'bg-level3 text-level1'),
            _Html(
                $transaction->description.($pmtNumber ? (' #'.$pmtNumber) : '')
            ),
            _Currency($transaction->amount)->class('whitespace-nowrap text-right')
                ->class($transaction->isReversed() ? 'line-through' : ''),
            _FlexEnd(
                $this->voidLinkWithAction($transaction) ?: _Html(),
            ),
        )->class('cursor-pointer')
        ->selfGet('getTransactionPreview', ['id' => $transaction->id])->inDrawer();
    }

    public function getTransactionPreview($id)
    {
        return new TransactionPreviewForm($id);
    }

    protected function voidLinkWithAction($transaction)
    {
        return;  //overridden
    }

    public function yearMonthLinkGroup()
    {
        if ($year = request('year')) {
            return _Flex4(
                _Link(__('Year').' '.$year)->class('text-level1 font-medium')->icon('arrow-left')
                    ->getElements('yearMonthLinkGroup')->inPanel('transactions-year-month-filter'),
                _LinkGroup()->name('month', false)->class('mb-0')
                    ->options(
                        $this->getTxCountFor($year)->mapWithKeys(fn($stat) => [
                            $stat->label => $this->yearMonthOption(carbon($stat->label.'-01', 'Y-m-d')->translatedFormat('M'), $stat->cn)
                        ])
                    )->selectedClass('text-greenmain border-b-2 border-level3', 'text-greenmain border-b-2 border-transparent')
                    ->filter()
            )->class('mb-4');
        }

        return _Flex4(
            _Html('finance.filter-by-year')->class('text-level1 font-medium'),
            _LinkGroup()->name('year', false)->class('mb-0')
                ->options(
                    $this->getTxCountFor()->mapWithKeys(fn($stat) => [
                        $stat->label => $this->yearMonthOption($stat->label, $stat->cn)
                    ])
                )->selectedClass('text-greenmain border-b-2 border-level3 !bg-gray-100', 'text-greenmain border-b-2 border-transparent !bg-gray-100')
                ->filter()
                ->onSuccess(fn($e) => $e->getElements('yearMonthLinkGroup')->inPanel('transactions-year-month-filter'))
        )->class('mb-4');
    }


    public function getTxCountFor($year = null)
    {
        $labelFunc = $year ? 'LEFT(transacted_at,7)' : 'YEAR(transacted_at)';

        $query = Transaction::selectRaw($labelFunc.' as label, COUNT(*) as cn')->where('team_id', currentTeamId())
            ->groupByRaw($labelFunc)->orderByRaw($labelFunc.' DESC');

        return ($year ? $query->whereRaw('YEAR(transacted_at) = ?', [$year]) : $query )->get();
    }

    protected function yearMonthOption($label, $count)
    {
        return _Html($label.' <span class="text-xs text-gray-600">('.$count.')</span>')->class('font-bold cursor-pointer mr-4');
    }
}

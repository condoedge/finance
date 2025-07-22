<?php

namespace Condoedge\Finance\Kompo\ExpenseReports;

use Condoedge\Finance\Models\ExpenseReport;
use Condoedge\Finance\Models\ExpenseReportStatusEnum;
use Condoedge\Utils\Kompo\Common\WhiteTable;
use Kompo\Auth\Facades\TeamModel;

class ExpenseReportsTable extends WhiteTable
{
    public $id = 'expense-reports-table';

    protected $asManager = true;

    public function query()
    {
        $query = ExpenseReport::where('is_draft', false)
            ->orderBy('created_at', 'desc');

        if (!request('team_id')) {
            $query->where('team_id', currentTeamId())
                ->when(currentTeam()->isGroupLevel(), function ($query) {
                    $query->whereIn('team_id', currentTeam()->getAllChildrenRawSolution());
                });
        } elseif (currentTeam()->isGroupLevel() && currentTeam()->getAllChildrenRawSolution()->contains(request('team_id'))) {
            $query->where('team_id', request('team_id'));
        }

        return $query->with('customer', 'team');
    }

    protected function header()
    {
        return _Html('translate.finance-expense-reports')->class('font-semibold text-2xl mb-6');
    }

    public function top()
    {
        return _Rows(
            $this->header(),
            _Flex(
                _InputSearch()->name('expense_title')
                    ->placeholder('finance-search-expense-reports')->filter(),
                _Select()
                    ->name('expense_status')
                    ->placeholder('finance-status')
                    ->options(ExpenseReportStatusEnum::optionsWithLabels())
                    ->filter(),
                !currentTeam()->isGroupLevel() || !$this->asManager ? null : _Select()
                    ->name('team_id')
                    ->placeholder('translate.finance-select-team')
                    ->options(TeamModel::whereIn('id', currentTeam()->getAllChildrenRawSolution())->pluck('team_name', 'id'))
                    ->filter()
            )->class('gap-6')
        );
    }

    public function headers()
    {
        return [
            _CheckAllItems(),
            _Th('finance-date'),
            _Th('finance-expense-title'),
            !$this->asManager ? null : _Th('finance-applicant-name'),
            _Th('finance-linked-to-this-team'),
            _Th('finance-status'),
            _Th('finance-total-amount')->sort('total_amount'),
            _Th()->class('w-8'),
        ];
    }

    public function render($expenseReport)
    {
        return _TableRow(
            _CheckSingleItem($expenseReport->id),
            _Html($expenseReport->created_at->format('Y-m-d')),
            _Html($expenseReport->expense_title),
            !$this->asManager ? null : _Html($expenseReport->customer->name),
            _Html($expenseReport->team->team_name),
            $expenseReport->expense_status->pill(),
            _FinanceCurrency($expenseReport->total_amount),
            _TripleDotsDropdown(
                $this->actionsButtons(),
            )
        )->when($this->canOpenModal(), fn ($el) => $el->selfGet('getExpenseReportModal', ['id' => $expenseReport->id])
            ->inModal());
    }

    protected function actionsButtons()
    {
        return [];
    }

    protected function canOpenModal()
    {
        return true;
    }

    public function getExpenseReportModal($expenseReportId)
    {
        return new ExpenseReportAnswerModal($expenseReportId);
    }
}

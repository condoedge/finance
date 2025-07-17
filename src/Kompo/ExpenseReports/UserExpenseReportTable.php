<?php

namespace Condoedge\Finance\Kompo\ExpenseReports;

use Condoedge\Finance\Models\ExpenseReport;
use Condoedge\Utils\Kompo\Common\WhiteTable;

class UserExpenseReportTable extends WhiteTable
{
    public $id = 'user-expense-report-table';

    public function top()
    {
        return _Rows(
            _FlexBetween(
                _Html('translate.my-expense-reports')->class('font-semibold text-2xl'),
                _Button('translate.create-expense-report')
                    ->selfGet('getExpenseReportForm')
                    ->inModal(),
            )->class('mb-6'),
            _Flex(
                _InputSearch()->name('expense_title')
                ->placeholder('translate.search-expense-reports')->filter(),
            )->class('gap-6')
        );
    }

    public function query()
    {
        if (!method_exists(auth()->user(), 'getCustomersRelated')) {
            return collect();
        }

        return ExpenseReport::whereIn('customer_id', auth()->user()->getCustomersRelated()->pluck('id'))
            ->where('is_draft', false);
    }

    public function headers()
    {
        return [
            _CheckAllItems(),
            _Th('translate.date'),
            _Th('translate.expense-title'),
            _Th('translate.linked-to-this-team'),
            _Th('translate.status'),
            _Th('translate.total-amount')->sort('total_amount'),
            _Th(),
        ];
    }

    public function render($expenseReport)
    {
        return _TableRow(
            _CheckSingleItem($expenseReport->id),
            _Html($expenseReport->created_at->format('Y-m-d')),
            _Html($expenseReport->expense_title),
            _Html($expenseReport->team->team_name),
            $expenseReport->expense_status->pill(),
            _FinanceCurrency($expenseReport->total_amount),
            _TripleDotsDropdown(),
        );
    }

    public function getExpenseReportForm()
    {
        return new ExpenseReportForm();
    }
}

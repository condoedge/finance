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
                _Html('finance-my-expense-reports')->class('font-semibold text-2xl'),
                _Button('finance-create-expense-report')
                    ->checkAuthWrite('manage_own_expense_report')
                    ->selfGet('getExpenseReportForm')
                    ->inModal(),
            )->class('mb-6'),
            _Flex(
                _InputSearch()->name('expense_title')
                    ->placeholder('finance-search-expense-reports')->filter(),
            )->class('gap-6')
        );
    }

    public function query()
    {
        if (!method_exists(auth()->user(), 'getCustomersRelated')) {
            return collect();
        }

        return ExpenseReport::whereIn('customer_id', auth()->user()->getCustomersRelated()->pluck('id'))
            ->where('is_draft', false)
            ->whereNotNull('sent_at');
    }

    public function headers()
    {
        return [
            _CheckAllItems(),
            _Th('finance-date'),
            _Th('finance-expense-title'),
            _Th('finance-linked-to-this-team'),
            _Th('finance-status'),
            _Th('finance-total-amount')->sort('total_amount'),
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
            _TripleDotsDropdown(
                _DeleteLink('finance-delete-expense-report')->class('text-red-500 hover:text-red-700')
                    ->checkAuthWrite('manage_own_expense_report')
                    ->byKey($expenseReport),
            ),
        )->when(
            auth()->user()->hasPermission('manage_own_expense_report'),
            fn ($row) =>
            $row->selfGet('getExpenseReportForm', $expenseReport->id)
                ->inModal()
        );
    }

    public function getExpenseReportForm($expenseReportId = null)
    {
        return new ExpenseReportForm($expenseReportId);
    }
}

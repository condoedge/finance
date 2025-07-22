<?php

namespace Condoedge\Finance\Kompo\ExpenseReports;

use Condoedge\Finance\Models\ExpenseReport;

class UserExpenseReportTable extends ExpenseReportsTable
{
    public $id = 'user-expense-report-table';
    protected $asManager = false;

    protected function header()
    {
        return _FlexBetween(
            _Html('finance-my-expense-reports')->class('font-semibold text-2xl'),
            _Button('finance-create-expense-report')
                ->checkAuthWrite('manage_own_expense_report')
                ->selfGet('getExpenseReportModal')
                ->inModal(),
        )->class('mb-6');
    }

    public function query()
    {
        if (!method_exists(auth()->user(), 'getCustomersRelated')) {
            return collect();
        }

        return ExpenseReport::whereIn('customer_id', auth()->user()->getCustomersRelated()->pluck('id'))
            ->where('is_draft', false)
            ->orderBy('created_at', 'desc');
    }

    protected function actionsButtons()
    {
        return [];
    }


    protected function canOpenModal()
    {
        return auth()->user()->hasPermission('manage_own_expense_report');
    }

    public function getExpenseReportModal($expenseReportId = null)
    {
        return new ExpenseReportForm($expenseReportId);
    }
}

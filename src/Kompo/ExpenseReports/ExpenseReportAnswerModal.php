<?php

namespace Condoedge\Finance\Kompo\ExpenseReports;

use Condoedge\Finance\Kompo\Common\Modal;
use Condoedge\Finance\Models\ExpenseReport;

class ExpenseReportAnswerModal extends Modal
{
    protected $_Title = 'translate.review-expense-report';
    public $model = ExpenseReport::class;

    public function body()
    {
        return _Rows(
            _Html(__('translate.with-values.expense-for-team', ['team_name' => $this->model->team->team_name]))
                ->class('mb-2 text-xl font-semibold'),

            _FlexBetween(
                _Rows(
                    _Html($this->model->expense_title)->class('font-semibold text-lg'),

                    _Html($this->model->expense_description)
                        ->class('text-gray-600 mb-4'),
                ),

                _FinanceCurrency($this->model->total_amount)
                    ->class('text-2xl font-bold mb-4'),
            ),

            _Rows(
                _Html('finance-expenses')->class('font-semibold mb-2'),
                new ExpensesQuery([
                    'expense_report_id' => $this->model->id,
                    'readonly' => true,
                ]),
            )->class('mb-4'),

            _FlexBetween(
                _Button('translate.finance-reject')->class('bg-danger flex-1')
                    ->selfPost('rejectExpenseReport')->closeModal()->refresh('expense-reports-table')
                    ->alert('translate.finance-rejected-expense-report'),

                _Button('translate.finance-approve')->class('flex-1')
                    ->selfPost('approveExpenseReport')->closeModal()->refresh('expense-reports-table')
                    ->alert('translate.finance-approved-expense-report'),
            )->class('gap-6'),
        );
    }

    public function rejectExpenseReport()
    {
        $this->model->reject();
    }

    public function approveExpenseReport()
    {
        $this->model->approve();
    }
}
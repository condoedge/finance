<?php

namespace Condoedge\Finance\Kompo\ExpenseReports;

use Condoedge\Finance\Models\Expense;
use Condoedge\Utils\Kompo\Common\Query;

class ExpensesQuery extends Query
{
    public $id = 'expenses-query';

    public $noItemsFound = 'finance-no-expenses-found';
    protected $expenseReportId;

    protected $readonly = false;

    public function created()
    {
        $this->expenseReportId = $this->prop('expense_report_id');

        $this->readonly = $this->prop('readonly');
    }

    public function query()
    {
        return Expense::where('expense_report_id', $this->expenseReportId)
            ->orderBy('expense_date', 'desc');
    }

    public function render($expense)
    {
        return _CardLevel5(
            _FlexBetween(
                _Html($expense->expense_date->format('Y-m-d'))->class('text-lg'),
                _FinanceCurrency($expense->total_expense_amount),
            )->class('mb-1'),
            _Html($expense->description)->class('text-sm'),
        )->p4()->when(!$this->readonly, function ($el) use ($expense) {
            return $el->selfGet('getExpenseForm', ['id' => $expense->id])->warnBeforeClose()->inModal();
        })->when($this->readonly, function ($el) use ($expense) {
            return $el->selfGet('getExpenseInfoModal', ['id' => $expense->id])->inModal();
        });
    }

    public function getExpenseForm($expenseId)
    {
        return new ExpenseForm($expenseId);
    }

    public function getExpenseInfoModal($expenseId)
    {
        return new ExpenseInfoModal($expenseId);
    }
}

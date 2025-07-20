<?php

namespace Condoedge\Finance\Kompo\ExpenseReports;

use Condoedge\Finance\Kompo\Common\Modal;
use Condoedge\Finance\Models\Expense;
use Condoedge\Finance\Models\ExpenseReportTypeEnum;

class ExpenseForm extends Modal
{
    protected $_Title = 'finance-add-an-expense';

    public $model = Expense::class;

    public function created()
    {
        $this->model->expense_report_id = $this->model->expense_report_id ?? $this->prop('expense_report_id');
    }

    public function beforeSave()
    {
        $this->model->expense_amount_before_taxes = request('expense_amount_before_taxes', 0);
        $this->model->total_expense_amount = request('total_expense_amount', 0);
    }

    public function body()
    {
        return _Rows(
            _Date('finance-expense-date')->name('expense_date')
                ->class('mb-4'),
            _InputDollar('finance-expense-amount-before-taxes')->name('expense_amount_before_taxes', false)
                ->default($this->model->expense_amount_before_taxes?->toFloat() ?? 0)
                ->class('mb-4'),
            _InputDollar('finance-total-expense-amount')->name('total_expense_amount', false)
                ->default($this->model->total_expense_amount?->toFloat() ?? 0)
                ->class('mb-4'),
            _Select('finance-expense-type')->name('expense_type')
                ->options(ExpenseReportTypeEnum::optionsWithLabels())
                ->class('mb-4'),
            _Textarea('finance-expense-description')->name('expense_description'),
            _MultiImage('finance-expense-images')
                ->name('files'),
            _SubmitButton('finance-save-expense')
                ->class('mt-4')
                ->closeModal()
                ->refresh(['expenses-query', 'expense-report-total']),
        );
    }

    public function rules()
    {
        return [
            'expense_date' => 'required|date',
            'expense_amount_before_taxes' => 'required|numeric|min:0',
            'total_expense_amount' => 'required|numeric|min:0|gte:expense_amount_before_taxes',
            'expense_type' => 'required|in:' . implode(',', array_map(fn ($case) => $case->value, ExpenseReportTypeEnum::cases())),
            'expense_description' => 'nullable|string|max:1000',
        ];
    }
}

<?php

namespace Condoedge\Finance\Kompo\ExpenseReports;

use Condoedge\Finance\Kompo\Common\Modal;
use Condoedge\Finance\Models\Expense;
use Condoedge\Finance\Models\ExpenseReportTypeEnum;

class ExpenseForm extends Modal
{
    protected $_Title = 'translate.add-an-expense';

    public $model = Expense::class;

    public function created()
    {
        $this->model->expense_report_id = $this->prop('expense_report_id');
    }

    public function body()
    {
        return _Rows(
            _Date('translate.expense-date')->name('expense_date')
                ->class('mb-4'),
            _InputDollar('translate.expense-amount-before-taxes')->name('expense_amount_before_taxes')
                ->class('mb-4'),
            _InputDollar('translate.total-expense-amount')->name('total_expense_amount')
                ->class('mb-4'),
            _Select('translate.expense-type')->name('expense_type')
                ->options(ExpenseReportTypeEnum::optionsWithLabels())
                ->class('mb-4'),
            _Textarea('translate.expense-description')->name('expense_description'),
            _SubmitButton('translate.save-expense')
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
            'expense_type' => 'required|in:' . implode(',', array_map(fn($case) => $case->value, ExpenseReportTypeEnum::cases())),
            'expense_description' => 'nullable|string|max:1000',
        ];
    }
}

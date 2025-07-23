<?php

namespace Condoedge\Finance\Kompo\ExpenseReports;

use Condoedge\Finance\Kompo\Common\Modal;
use Condoedge\Finance\Models\Expense;

class ExpenseInfoModal extends Modal
{
    protected $_Title = 'finance-expense-report-info';
    public $model = Expense::class;

    public function body()
    {
        return _Rows(
            !$this->model->description ? null : _Html($this->model->description)->class('mb-4'),
            _FlexBetween(
                _Html('finance-date'),
                _Html($this->model->expense_date->format('Y-m-d')),
            )->class('text-lg font-semibold gap-4 mb-4'),
            _Flex(
                collect($this->model->files)->map(function ($file) {
                    return _Img($file->link)->class('h-48 object-cover');
                }),
            )->style('max-width: 400px;')->class('gap-4 mini-scroll overflow-x-auto'),
            _FlexEnd(
                _FinanceCurrency($this->model->total_expense_amount)->class('text-2xl font-bold mb-4'),
            ),
        );
    }
}

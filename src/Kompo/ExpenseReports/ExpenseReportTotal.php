<?php

namespace Condoedge\Finance\Kompo\ExpenseReports;

use Condoedge\Finance\Models\ExpenseReport;
use Condoedge\Utils\Kompo\Common\Form;

class ExpenseReportTotal extends Form
{
    public $id = 'expense-report-total';

    public $model = ExpenseReport::class;

    public function render()
    {
        if (!$this->model->total_amount) {
            return null;
        }

        return _FlexBetween(
            _Html('translate.total')->class('font-semibold text-2xl uppercase'),
            _FinanceCurrency($this->model->total_amount)
                ->class('text-2xl font-bold')
        )->class('mb-4');
    }
}

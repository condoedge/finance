<?php

namespace Condoedge\Finance\Kompo\ExpenseReports;

use Condoedge\Finance\Kompo\Common\Modal;
use Condoedge\Finance\Models\ExpenseReportType;

class ExpenseReportTypeForm extends Modal
{
    public $_Title = 'finance-expense-report-type-form';
    public $model = ExpenseReportType::class;

    protected $teamId;

    public function created()
    {
        $this->teamId = $this->prop('team_id');
    }

    public function beforeSave()
    {
        $this->model->team_id = $this->teamId;
    }

    public function body()
    {
        return _Rows(
            _Translatable('finance-expense-report-type-name')->name('name')->required(),

            _SubmitButton('generic.save')->closeModal()->browse('expense-report-types-table')
                ->alert('finance-expense-report-type-saved'),
        );
    }

    public function rules()
    {
        return [
            'name' => 'required',
        ];
    }
}

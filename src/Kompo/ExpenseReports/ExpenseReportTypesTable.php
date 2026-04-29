<?php

namespace Condoedge\Finance\Kompo\ExpenseReports;

use Condoedge\Finance\Models\ExpenseReportType;
use Condoedge\Utils\Kompo\Common\WhiteTable;

class ExpenseReportTypesTable extends WhiteTable
{
    public $id = 'expense-report-types-table';

    protected $teamId;

    public function created()
    {
        $this->teamId = $this->prop('team_id');
    }

    public function query()
    {
        return ExpenseReportType::forTeam($this->teamId);
    }

    public function top()
    {
        return _Rows(
            _Button('finance-add-expense-report-type')
                ->selfGet('getExpenseReportTypeForm')->inModal()
        )->class('mb-4');
    }

    public function headers()
    {
        return [
            _Th('finance-expense-report-type-name'),
            _Th()->class('w-8')
        ];
    }

    public function render($expenseReportType)
    {
        return _TableRow(
            _Html($expenseReportType->name),
            _TripleDotsDropdown(
                _Link('finance-edit')->selfGet('getExpenseReportTypeForm', ['id' => $expenseReportType->id])->inModal(),
                _DeleteLink('finance-delete')->byKey($expenseReportType)->class('text-red-600 hover:text-red-800'),
            )
        );
    }

    public function getExpenseReportTypeForm($id = null)
    {
        return new ExpenseReportTypeForm($id, [
            'team_id' => $this->teamId
        ]);
    }
}

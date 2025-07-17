<?php

namespace Condoedge\Finance\Kompo\ExpenseReports;

use Condoedge\Finance\Kompo\Common\Modal;
use Condoedge\Finance\Models\ExpenseReport;
use Kompo\Auth\Facades\TeamModel;

class ExpenseReportForm extends Modal
{
    protected $_Title = 'translate.create-expense-report';

    public $model = ExpenseReport::class;

    public function created()
    {
        if (!$this->model->id) {
            $expenseReport = new ExpenseReport();
            $expenseReport->expense_title = 'das';
            $expenseReport->user_id = auth()->id();
            $expenseReport->customer_id = auth()->user()->getCurrentCustomer()->id;
            $expenseReport->team_id = currentTeamId() ?? null;
            $expenseReport->is_draft = true; // Assuming you want to create a draft
            $expenseReport->save();

            $this->model($expenseReport);
        }
    }

    public function beforeSave()
    {
        $this->model->is_draft = false;
    }

    public function body()
    {
        return _Rows(
            new ExpenseReportTotal($this->model->id),
            _Input('translate.expense-title')->name('expense_title')
                ->class('mb-4'),
            _Select('translate.team')->name('team_id')
                ->searchOptions(2, 'searchTeams'),
            _Textarea('translate.expense-description')->name('expense_description')
                ->class('mb-4'),
            _Rows(
                _Html('translate.expenses')->class('text-lg'),
                _Rows(new ExpensesQuery([
                    'expense_report_id' => $this->model->id,
                ]))->class('text-center'),
                _ButtonOutlined('translate.add-expense')
                    ->selfGet('getExpenseForm')
                    ->inModal()
                    ->class('mt-2 mb-4'),
            ),
            _SubmitButton('translate.save-expense-report')
                ->class('mt-4')
                ->closeModal()
                ->refresh(['user-expense-report-table']),
        );
    }

    public function searchTeams($search)
    {
        $teamsIds = auth()->user()->getAllAccessibleTeamIds($search, 30);

        return TeamModel::whereIn('id', $teamsIds)
            ->pluck('team_name', 'id');
    }

    public function getExpenseForm()
    {
        return new ExpenseForm([
            'expense_report_id' => $this->model->id,
        ]);
    }
}

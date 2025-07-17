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
            $this->model($this->createDraftExpenseReport());
        }
    }

    public function beforeSave()
    {
        if (!$this->model->expenses()->count()) {
            abort(403, __('translate.expense-report-must-have-at-least-one-expense'));
        }

        $this->model->is_draft = false;
        $this->model->customer_id = auth()->user()->getCurrentCustomer(request('team_id'))->id;
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
                _Html('translate.expenses')->class('text-lg mb-2'),

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
        return new ExpenseForm(null, [
            'expense_report_id' => $this->model->id,
        ]);
    }

    protected function createDraftExpenseReport()
    {
        $expenseReport = ExpenseReport::where('user_id', auth()->id())
            ->where('is_draft', true)
            ->first();

        if (!$expenseReport) {
            $expenseReport = new ExpenseReport();
            $expenseReport->expense_title = '';
            $expenseReport->user_id = auth()->id();
            $expenseReport->customer_id = auth()->user()->getCurrentCustomer()->id;
            $expenseReport->team_id = currentTeamId() ?? null;
            $expenseReport->is_draft = true;
            $expenseReport->save();
        }

        return $expenseReport;
    }

    public function rules()
    {
        return [
            'expense_title' => 'required|string|max:255',
            'team_id' => 'required|exists:teams,id',
            'expense_description' => 'nullable|string|max:1000',
        ];
    }
}

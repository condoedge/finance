<?php

namespace Condoedge\Finance\Kompo\ExpenseReports;

use Condoedge\Finance\Kompo\Common\Modal;
use Condoedge\Finance\Models\ExpenseReport;
use Condoedge\Finance\Models\ExpenseReportStatusEnum;
use Kompo\Auth\Facades\TeamModel;

class ExpenseReportForm extends Modal
{
    protected $_Title = 'finance-create-expense-report';

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
            abort(403, __('error-expense-report-must-have-at-least-one-expense'));
        }

        $this->model->is_draft = false;
        $this->model->customer_id = auth()->user()->getCurrentCustomer(request('team_id'))->id;

        $this->model->expense_status = ExpenseReportStatusEnum::PENDING;
    }

    public function body()
    {
        $editable = $this->isEditable();

        return _Rows(
            $this->statusBanner(),
            new ExpenseReportTotal($this->model->id),
            _Input('finance-expense-title')->name('expense_title')
                ->when(!$editable, fn ($el) => $el->readOnly())
                ->class('mb-4'),
            _Select('finance-team')->name('team_id')
                ->searchOptions(2, 'searchTeams')
                ->when(!$editable, fn ($el) => $el->readOnly()),
            _Textarea('finance-expense-description')->name('expense_description')
                ->when(!$editable, fn ($el) => $el->readOnly())
                ->class('mb-4'),
            _Rows(
                _Html('finance-expenses')->class('text-lg mb-2'),
                _Rows(new ExpensesQuery([
                    'expense_report_id' => $this->model->id,
                    'readonly' => !$editable,
                ]))->class('text-center'),
                !$editable ? null : _ButtonOutlined('finance-add-expense')
                    ->selfGet('getExpenseForm')
                    ->warnBeforeClose()
                    ->inModal()
                    ->class('mt-2 mb-4'),
            ),
            !$editable ? null : _SubmitButton('finance-save-expense-report')
                ->class('mt-4')
                ->closeModal()
                ->refresh(['user-expense-report-table']),
        );
    }

    // Only true while the report is still a draft. Once submitted (even if
    // later rejected) it's read-only here. A new report is the way to resubmit.
    protected function isEditable(): bool
    {
        return (bool) $this->model->is_draft;
    }

    // Status pill + review note shown when the report is no longer a draft.
    // This is the submitter's only window into the approver's decision.
    protected function statusBanner()
    {
        if ($this->model->is_draft || !$this->model->expense_status) {
            return null;
        }

        return _Rows(
            _FlexBetween(
                _Html('finance-status')->class('font-semibold'),
                $this->model->expense_status->pill(),
            )->class('mb-2'),
            !$this->model->review_note ? null : _Rows(
                _Html('finance-review-note')->class('font-semibold mb-1'),
                _Html($this->model->review_note)
                    ->class('text-gray-700 whitespace-pre-line p-3 bg-gray-50 rounded'),
            ),
        )->class('mb-4 p-3 border rounded');
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

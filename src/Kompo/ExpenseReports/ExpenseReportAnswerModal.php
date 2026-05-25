<?php

namespace Condoedge\Finance\Kompo\ExpenseReports;

use Condoedge\Finance\Kompo\Common\Modal;
use Condoedge\Finance\Models\ExpenseReport;
use Condoedge\Finance\Models\ExpenseReportStatusEnum;

class ExpenseReportAnswerModal extends Modal
{
    protected $_Title = 'finance-review-expense-report';
    public $model = ExpenseReport::class;

    public function body()
    {
        return _Rows(
            _Html(__('finance-with-values-expense-for-team', ['team_name' => $this->model->team->team_name]))
                ->class('mb-2 text-xl font-semibold'),
            _FlexBetween(
                _Rows(
                    _Html($this->model->expense_title)->class('font-semibold text-lg'),
                    _Html($this->model->expense_description)
                        ->class('text-gray-600 mb-4'),
                ),
                _Rows(
                    $this->model->expense_status->pill()->class('mb-2 ml-auto'),
                    _FinanceCurrency($this->model->total_amount)
                        ->class('text-2xl font-bold mb-4'),
                ),
            ),
            _Rows(
                _Html('finance-expenses')->class('font-semibold mb-2'),
                new ExpensesQuery([
                    'expense_report_id' => $this->model->id,
                    'readonly' => true,
                ]),
            )->class('mb-4'),
            $this->reviewBlock(),
        );
    }

    // Show note input + action buttons depending on status.
    // PENDING  → editable note + Approve/Reject (reject requires non-empty).
    // APPROVED → previous note (read-only) + Mark as Paid.
    // REJECTED → previous note (read-only), no actions.
    // PAID     → previous note (read-only), no actions.
    protected function reviewBlock()
    {
        $status = $this->model->expense_status;

        if ($status === ExpenseReportStatusEnum::PENDING) {
            return _Rows(
                _Textarea('finance-review-note')->name('review_note', false)
                    ->placeholder('finance-review-note-placeholder')
                    ->class('mb-4'),
                _FlexBetween(
                    _Button('finance-reject')->class('bg-danger flex-1')
                        ->selfPost('rejectExpenseReport')->withAllFormValues()
                        ->closeModal()->refresh('expense-reports-table')
                        ->alert('finance-rejected-expense-report'),
                    _Button('finance-approve')->class('flex-1')
                        ->selfPost('approveExpenseReport')->withAllFormValues()
                        ->closeModal()->refresh('expense-reports-table')
                        ->alert('finance-approved-expense-report'),
                )->class('gap-6'),
            );
        }

        return _Rows(
            $this->reviewNoteReadOnly(),
            $status === ExpenseReportStatusEnum::APPROVED
                ? _Button('finance-mark-as-paid')->class('flex-1')
                    ->selfPost('markAsPaidExpenseReport')
                    ->closeModal()->refresh('expense-reports-table')
                    ->alert('finance-paid-expense-report')
                : null,
        );
    }

    protected function reviewNoteReadOnly()
    {
        if (!$this->model->review_note) {
            return null;
        }

        return _Rows(
            _Html('finance-review-note')->class('font-semibold mb-1'),
            _Html($this->model->review_note)
                ->class('text-gray-700 whitespace-pre-line p-3 bg-gray-50 rounded mb-4'),
        );
    }

    public function rejectExpenseReport()
    {
        $this->model->reject((string) request('review_note', ''));
    }

    public function approveExpenseReport()
    {
        $this->model->approve(request('review_note'));
    }

    public function markAsPaidExpenseReport()
    {
        $this->model->markAsPaid();
    }
}

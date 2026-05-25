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
            $this->filesBlock(),
            _FlexEnd(
                _FinanceCurrency($this->model->total_expense_amount)->class('text-2xl font-bold mb-4'),
            ),
        );
    }

    // Image thumbnail for images; clickable filename + icon for everything else
    // (PDFs, docs, etc.). _MultiFile now allows the submitter to attach these.
    protected function filesBlock()
    {
        $files = collect($this->model->files);

        if ($files->isEmpty()) {
            return null;
        }

        return _Flex(
            $files->map(function ($file) {
                return $this->isImage($file)
                    ? _Img($file->link)->class('h-48 object-cover rounded shrink-0')
                    : _Link($file->name ?: __('finance-attachment'))
                        ->href($file->link)->inNewTab()
                        ->icon('document')
                        ->class('shrink-0 p-3 border rounded hover:bg-gray-50');
            }),
        )->style('max-width: 400px;')->class('gap-4 mini-scroll overflow-x-auto mb-4');
    }

    protected function isImage($file): bool
    {
        if (!empty($file->mime_type) && str_starts_with($file->mime_type, 'image/')) {
            return true;
        }

        $ext = strtolower(pathinfo((string) ($file->name ?? $file->path ?? ''), PATHINFO_EXTENSION));
        return in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'], true);
    }
}

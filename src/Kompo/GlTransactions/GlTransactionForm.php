<?php

namespace Condoedge\Finance\Kompo\GlTransactions;

use Condoedge\Finance\Models\GlTransactionHeader;
use Condoedge\Finance\Models\Dto\CreateGlTransactionDto;
use Condoedge\Finance\Models\SegmentValue;
use Kompo\Form;

class GlTransactionForm extends Form
{
    public $model = GlTransactionHeader::class;

    protected $isViewOnly = false;

    public function created()
    {
        $this->isViewOnly = $this->model->exists && $this->model->is_posted;
    }

    public function render()
    {
        return _Rows(
            _FlexBetween(
                _TitleMain(
                    $this->model->exists ?
                        __('finance-gl-transaction') . ' #' . $this->model->gl_transaction_number :
                        __('finance-create-gl-transaction')
                ),
                _Link(__('finance-back-to-list'))
                    ->icon('arrow-left')
                    ->href('finance.gl.gl-transactions')
            )->class('mb-6'),

            $this->isViewOnly ?
                _Alert(__('finance-posted-transaction-readonly'))->warning() : null,

            _Card(
                _Columns(
                    _Date(__('finance-transaction-date'))
                        ->name('gl_transaction_date')
                        ->required()
                        ->disabled($this->isViewOnly)
                        ->default(now()),

                    _Select(__('finance-transaction-type'))
                        ->name('gl_transaction_type')
                        ->options([
                            1 => __('finance-manual-entry'),
                            2 => __('finance-bank-transaction'),
                            3 => __('finance-receivables'),
                            4 => __('finance-payables'),
                        ])
                        ->disabled($this->isViewOnly)
                        ->required(),
                )->class('gap-4 mb-4'),

                _Textarea(__('finance-description'))
                    ->name('gl_transaction_description')
                    ->rows(2)
                    ->disabled($this->isViewOnly),
            )->class('mb-4'),

            // Transaction lines
            _Card(
                _TitleMini(__('finance-transaction-lines'))->class('mb-4'),

                _MultiForm()->noLabel()->name('invoiceDetails')
                    ->formClass(GlTransactionLineForm::class, [
                        'team_id' => currentTeam(),
                    ])
                    ->asTable([
                        __('finance-product-service'),
                        '',
                        _FlexBetween(
                            _Flex(
                                _Th('finance-quantity')->class('w-28'),
                                _Th('finance-price')->class('w-28'),
                            )->class('space-x-4'),
                            _Th('finance-total')->class('text-right'),
                        )->class('text-sm font-medium'),
                    ])
                    ->class('mb-6 bg-white rounded-2xl')
                    ->id('finance-items'),
            ),

            // Actions
            !$this->isViewOnly ?
                _FlexEnd(
                    _SubmitButton(__('finance-save-draft'))
                        ->outlined()
                        ->selfPost('saveDraft'),
                    _SubmitButton(__('finance-save-and-post'))
                        ->selfPost('saveAndPost'),
                )->class('mt-4 gap-3') : null,
        );
    }

    protected function renderTransactionLines()
    {
        $lines = $this->model->exists ?
            $this->model->glTransactionLines :
            collect([]);

        if ($lines->isEmpty() && !$this->isViewOnly) {
            // Add two empty lines for new transactions
            return _Rows(
                $this->renderTransactionLine(0),
                $this->renderTransactionLine(1),
            )->id('lines-container');
        }

        return _Rows(
            $lines->map(
                fn($line, $index) =>
                $this->renderTransactionLine($index, $line)
            )
        )->id('lines-container');
    }

    protected function renderTransactionLine($index, $line = null)
    {
        return _Columns(
            _Select()->placeholder('account')
                ->class('w-36 !mb-0')
                ->name('revenue_segment_account_id')
                ->options(SegmentValue::forLastSegment()->get()->mapWithKeys(
                    fn($it) => [$it->id => $it->segment_value . ' - ' . $it->segment_description]
                )),

            _Input(__('finance-debit'))
                ->name("lines[{$index}][debit_amount]")
                ->type('number')
                ->step('0.01')
                ->value($line?->debit_amount)
                ->disabled($this->isViewOnly)
                ->placeholder('0.00'),

            _Input(__('finance-credit'))
                ->name("lines[{$index}][credit_amount]")
                ->type('number')
                ->step('0.01')
                ->value($line?->credit_amount)
                ->disabled($this->isViewOnly)
                ->placeholder('0.00'),

            !$this->isViewOnly ?
                _Button()->icon('trash')
                ->class('text-danger')
                ->selfPost('removeLine', ['index' => $index])
                ->inPanel('transaction-lines-panel') : null,
        )->class('gap-2 mb-2 items-end')
            ->id("line-{$index}");
    }

    public function addTransactionLine()
    {
        $currentLines = collect(request('lines', []));
        $newIndex = $currentLines->count();

        return _Rows(
            $currentLines->map(
                fn($line, $index) =>
                $this->renderTransactionLine($index, (object)$line)
            )->push(
                $this->renderTransactionLine($newIndex)
            )
        )->id('lines-container');
    }

    public function removeLine($index)
    {
        $lines = collect(request('lines', []))
            ->forget($index)
            ->values();

        return _Rows(
            $lines->map(
                fn($line, $idx) =>
                $this->renderTransactionLine($idx, (object)$line)
            )
        )->id('lines-container');
    }

    public function saveDraft()
    {
        $this->saveTransaction(false);
    }

    public function saveAndPost()
    {
        $this->saveTransaction(true);
    }

    protected function saveTransaction($shouldPost)
    {
        try {
            $dto = new CreateGlTransactionDto(array_merge(
                request()->only(['gl_transaction_date', 'gl_transaction_type', 'gl_transaction_description']),
                [
                    'lines' => collect(request('lines', []))->filter(function ($line) {
                        return !empty($line['account_id']) &&
                            (!empty($line['debit_amount']) || !empty($line['credit_amount']));
                    })->values()->toArray(),
                    'should_post' => $shouldPost,
                ]
            ));

            $service = app(\Condoedge\Finance\Services\GlTransactionService::class);

            if ($this->model->exists) {
                $transaction = $service->updateTransaction($this->model, $dto);
            } else {
                $transaction = $service->createManualGlTransaction($dto);
            }

            if ($shouldPost) {
                $service->postTransaction($transaction);
            }

            $this->notifySuccess(__('finance-transaction-saved-successfully'));

            return redirect()->route('finance.gl.gl-transactions');
        } catch (\Exception $e) {
            $this->notifyError($e->getMessage());
        }
    }

    public function rules()
    {
        return [
            'gl_transaction_date' => 'required|date',
            'gl_transaction_type' => 'required|integer|between:1,4',
            'gl_transaction_description' => 'nullable|string|max:500',
            'lines' => 'required|array|min:2',
            'lines.*.account_id' => 'required|exists:fin_gl_accounts,account_id',
            'lines.*.debit_amount' => 'nullable|numeric|min:0',
            'lines.*.credit_amount' => 'nullable|numeric|min:0',
        ];
    }
}

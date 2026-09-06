<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Facades\InvoiceModel;
use Condoedge\Finance\Facades\InvoiceService;
use Condoedge\Finance\Kompo\Common\Modal;
use Condoedge\Finance\Models\Dto\Invoices\VoidManyInvoicesDto;

/**
 * Void a selection. The gate is heavier than the single-invoice one for the obvious
 * reason: the count has to be typed back, so confirming means having read how many
 * invoices are about to be cancelled.
 *
 * What cannot be voided is listed with its reason instead of blocking the batch — a
 * treasurer clearing forty drafts should not be stopped by one paid invoice in the
 * selection, but must see that it will be left alone.
 */
class VoidManyInvoicesModal extends Modal
{
    public $class = 'max-w-2xl';

    protected $voidable;
    protected $skipped;

    protected $refreshId;

    public function created()
    {
        $this->refreshId = $this->prop('refresh_id');
        
        // Scoped to the team: these ids arrive from the browser, and the batch must not
        // reach past what the table itself would list.
        $ids = array_filter(explode(',', (string) $this->prop('invoice_to_void_ids')));

        $invoices = !$ids ? collect() : InvoiceModel::forTeam(currentTeamId())
            ->whereIn('id', $ids)->get();

        $this->voidable = $invoices->filter->canBeVoided()->values();
        $this->skipped = $invoices->reject->canBeVoided()->values();

        $this->_Title = __('finance-void-invoices');
    }

    public function handle()
    {
        InvoiceService::voidMany(new VoidManyInvoicesDto([
            'invoices_ids' => $this->voidable->pluck('id')->all(),
        ]));
    }

    public function body()
    {
        if (!$this->voidable->count()) {
            return [
                _Html('finance-void-many-nothing-eligible')->class('mb-4'),
                $this->skippedList(),
            ];
        }

        return [
            _CardLevel5(
                _FinanceCurrency($this->voidableTotal())->class('font-bold text-3xl'),
                _Html(__('finance-with-values-void-many-count', [
                    'count' => $this->voidable->count(),
                ]))->class('text-lg font-semibold'),
            )->p4()->alignEnd(),

            _Html('finance-void-many-explanation')->class('mb-4'),

            $this->skippedList(),

            _Html('finance-void-is-irreversible')->class('mb-4 font-semibold text-danger'),

            _Input(__('finance-with-values-type-count-to-confirm', [
                'count' => $this->voidable->count(),
            ]))->name('confirm_count', false),

            _Checkbox('finance-void-acknowledgement-many')->name('acknowledged', false),

            _FlexEnd(
                _SubmitButton('finance-void-invoices')->class('!bg-danger text-white')
                    ->closeModal()->browse($this->refreshId),
            ),
        ];
    }

    /** What the batch will leave alone, and why — stated before confirming, not after. */
    protected function skippedList()
    {
        if (!$this->skipped->count()) {
            return null;
        }

        return _Rows(
            _Html(__('finance-with-values-void-many-skipped', [
                'count' => $this->skipped->count(),
            ]))->class('font-semibold mb-2'),
            _Rows(
                $this->skipped->map(fn ($invoice) => _Flex4(
                    _Html($invoice->invoice_reference)->class('font-semibold w-32'),
                    _Html(__($invoice->voidRefusalReason()))->class('text-sm text-level1'),
                )->class('py-1')),
            ),
        )->class('mb-4 p-4 bg-level5 rounded-lg');
    }

    protected function voidableTotal()
    {
        return $this->voidable->reduce(
            fn ($carry, $invoice) => $carry->add($invoice->abs_invoice_total_amount),
            safeDecimal(0),
        );
    }

    public function rules()
    {
        return [
            // Typing the count is the gate: you cannot confirm without having read it.
            'confirm_count' => 'required|in:' . $this->voidable->count(),
            'acknowledged' => 'accepted',
        ];
    }

    public function messages()
    {
        return [
            'confirm_count.in' => __('finance-void-count-mismatch'),
            'confirm_count.required' => __('finance-void-count-mismatch'),
            'acknowledged.accepted' => __('finance-void-acknowledgement-required'),
        ];
    }
}

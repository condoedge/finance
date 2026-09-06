<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Facades\InvoiceModel;
use Condoedge\Finance\Facades\InvoiceService;
use Condoedge\Finance\Kompo\Common\Modal;
use Condoedge\Finance\Models\Dto\Invoices\VoidInvoiceDto;

/**
 * Cancel an invoice for good, behind two gates: the reference has to be typed back and
 * the consequence acknowledged. Nothing here can be undone afterwards, which is why the
 * modal states what will actually happen rather than asking "are you sure?".
 */
class VoidInvoiceModal extends Modal
{
    protected $_Title = 'finance-void-invoice';

    public $model = InvoiceModel::class;

    protected $refreshId;

    public function created()
    {
        $this->refreshId = $this->prop('refresh_id');
    }

    public function handle()
    {
        InvoiceService::voidInvoice(new VoidInvoiceDto([
            'invoice_id' => $this->model->id,
        ]));
    }

    public function body()
    {
        return [
            _CardLevel5(
                _FinanceCurrency($this->model->abs_invoice_total_amount)->class('font-bold text-3xl'),
                _Html($this->model->invoice_reference)->class('text-lg font-semibold'),
            )->p4()->alignEnd(),

            _Html($this->consequence())->class('mb-4'),

            _Html('finance-void-is-irreversible')->class('mb-4 font-semibold text-danger'),

            _Input(__('finance-with-values-type-reference-to-confirm', [
                'reference' => $this->model->invoice_reference,
            ]))->name('confirm_reference', false),

            _Checkbox('finance-void-acknowledgement')->name('acknowledged', false),

            _ErrorField()->name('invoice_id', false)->noInputWrapper()->class('!my-0'),

            _FlexEnd(
                _SubmitButton('finance-void-invoice')->class('!bg-danger text-white')
                    ->refresh($this->refreshId)->closeModal(),
            ),
        ];
    }

    /**
     * Say what this void will do, because the two cases differ: a draft was never issued,
     * so nothing is reversed and no document is produced.
     */
    protected function consequence(): string
    {
        return __($this->model->is_draft
            ? 'finance-void-draft-explanation'
            : 'finance-void-approved-explanation');
    }

    public function rules()
    {
        return [
            // The reference is the gate, not a formality: it makes voiding the wrong
            // invoice take a deliberate act rather than a mis-click.
            'confirm_reference' => 'required|in:' . $this->model->invoice_reference,
            'acknowledged' => 'accepted',
        ];
    }

    public function messages()
    {
        return [
            'confirm_reference.in' => __('finance-void-reference-mismatch'),
            'confirm_reference.required' => __('finance-void-reference-mismatch'),
            'acknowledged.accepted' => __('finance-void-acknowledgement-required'),
        ];
    }
}

<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Facades\InvoiceModel;
use Condoedge\Finance\Facades\InvoiceService;
use Condoedge\Finance\Kompo\Common\Modal;
use Condoedge\Finance\Models\Dto\Invoices\CreateCreditNoteDto;
use Illuminate\Validation\ValidationException;

/**
 * Credit an invoice in one step: its lines come pre-cloned and individually selectable,
 * amounts stated positive, and the credit is approved and applied on save.
 *
 * The inputs are UNIT prices, signed as they read on the invoice. createCreditNote mirrors
 * the set once and multiplies by the source quantity, so an extended price would credit a
 * 3 x $40 line as $360, and an abs() would credit a rebated invoice for more than it owes.
 */
class CreditNoteForm extends Modal
{
    public $class = 'max-w-2xl';

    protected $invoiceId;
    protected $invoice;

    public function created()
    {
        $this->invoiceId = $this->prop('invoice_id');
        $this->invoice = InvoiceModel::findOrFail($this->invoiceId);

        $this->_Title = __('finance-with-values-credit-note-for-invoice', [
            'reference' => $this->invoice->invoice_reference,
        ]);
    }

    public function handle()
    {
        $checked = collect(getTableInputValues('credit_line_'))->filter()->keys();

        // An empty lines array is indistinguishable from "clone everything" in the service,
        // and it would also skip the DTO's over-credit guard.
        if ($checked->isEmpty()) {
            throw ValidationException::withMessages(['lines' => __('finance-select-at-least-one-line')]);
        }

        $prices = collect(getTableInputValues('credit_unit_price_'));

        $lines = $this->invoice->invoiceDetails->whereIn('id', $checked)->map(fn ($detail) => [
            'name' => $detail->name,
            'description' => $detail->description,
            'quantity' => $detail->quantity,
            'unit_price' => (float) ($prices[$detail->id] ?? 0),
            'revenue_account_id' => $detail->revenue_account_id,
            'taxesIds' => $detail->invoiceTaxes->pluck('tax_id')->all(),
        ])->values()->all();

        $credit = InvoiceService::createCreditNote(new CreateCreditNoteDto([
            'credited_invoice_id' => $this->invoice->id,
            'invoice_date' => request('invoice_date'),
            'apply_to_invoice' => (bool) request('apply_to_invoice'),
            'lines' => $lines,
        ]));

        return redirect()->route('invoices.show', ['id' => $credit->id]);
    }

    public function body()
    {
        $stillOwing = $this->invoice->invoice_due_amount->greaterThan(0);

        return [
            _CardLevel5(
                _FinanceCurrency($this->invoice->abs_invoice_total_amount)->class('font-bold text-3xl'),
                _Flex4(
                    _Html('finance-still-owing'),
                    _FinanceCurrency($this->invoice->abs_invoice_due_amount)->class('font-semibold'),
                )->class('text-sm'),
            )->p4()->alignEnd(),

            _Date('finance-date')->name('invoice_date')->default(date('Y-m-d')),

            _Rows(
                $this->invoice->invoiceDetails->map(fn ($detail) => $this->lineRow($detail)),
            )->class('mb-4 divide-y divide-level5'),

            _ErrorField()->name('lines', false)->noInputWrapper()->class('!my-0'),

            _Checkbox(__('finance-with-values-apply-immediately-to-invoice', [
                'reference' => $this->invoice->invoice_reference,
            ]))->name('apply_to_invoice')
                ->default($stillOwing ? 1 : 0)
                ->when(!$stillOwing, fn ($e) => $e->disabled()),

            $stillOwing ? null :
                _Html('finance-invoice-already-paid-credit-stays-on-account')->class('text-sm text-level1'),

            _FlexEnd(
                _SubmitButton('finance-create-credit-note'),
            ),
        ];
    }

    protected function lineRow($detail)
    {
        return _FlexBetween(
            _Flex4(
                _Checkbox()->name('credit_line_' . $detail->id)->default(1)->class('!mb-0'),
                _Rows(
                    _Html($detail->name)->class('font-semibold'),
                    _Html($detail->quantity . ' ×')->class('text-sm text-level1'),
                ),
            ),
            // Signed as it reads on the invoice: a rebate line shows negative and
            // reverses as a positive on the credit.
            _InputDollar()->name('credit_unit_price_' . $detail->id)
                ->default($detail->unit_price->toFloat())
                ->class('w-32 !mb-0'),
        )->class('py-3');
    }

    public function rules()
    {
        return [
            'invoice_date' => 'required|date',
        ];
    }
}

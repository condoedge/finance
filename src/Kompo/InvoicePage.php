<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Facades\InvoiceModel;
use Condoedge\Finance\Facades\InvoiceService;
use Condoedge\Finance\Kompo\PaymentTerms\PaymentInstallmentPeriodsTable;
use Condoedge\Finance\Models\Dto\Invoices\ApproveInvoiceDto;
use Condoedge\Finance\Models\GlobalScopesTypes\Credit;
use Condoedge\Finance\Models\Invoice;
use Condoedge\Finance\Models\MorphablesEnum;
use Kompo\Form;

class InvoicePage extends Form
{
    /**
     * @var Invoice
     */
    public $model = InvoiceModel::class;

    protected $bigClass = 'text-xl font-medium text-level1';

    public $id = 'invoice-page'; //shared with bill stage form

    public function render()
    {
        return [
            _FlexBetween(
                _Breadcrumbs(
                    _Link('finance-all-receivables')->href('invoices.list'),
                    _Html($this->model->invoice_type_id->label() . ' ' . $this->model->invoice_reference),
                ),
                _FlexEnd4(
                    _Dropdown('finance-actions')->rIcon('icon-down')->button()
                        ->submenu(
                            _DropdownLink('finance-new-transaction')
                                ->href('invoices.form'),
                            $this->canBeCredited() ? _DropdownLink('finance-create-credit-note')
                                ->selfGet('getCreditNoteModal')->inModal() : null,
                        )->alignRight(),
                    !$this->model->is_draft ? null : _Link('finance-edit-invoice')->outlined()
                        ->href('invoices.form', ['id' => $this->model->id]),
                )
            )->class('mb-12'),
            _FlexBetween(
                _FlexEnd(
                    _Rows(
                        _MiniLabel('finance-status'),
                        $this->model->invoice_status_id->pill($this->model),
                    ),
                    _Rows(
                        _MiniLabel('finance-client'),
                        _Html($this->model->customer_label)->class($this->bigClass),
                        $this->relatedDocumentLink(),
                    )->class('border-l border-level4 pl-4'),
                )->class('space-x-8'),
                _FlexEnd4(
                    // A credit note has no due date, so the label would sit there empty.
                    $this->model->isRefund() ? null :
                        _MiniLabelDate('finance-invoice-due-date', $this->model->invoice_due_date, $this->bigClass),
                    _MiniLabelDate('finance-date', $this->model->invoice_date, $this->bigClass)->class('border-l border-level3 pl-4'),
                    _MiniLabelFinanceCcy('finance-total', $this->model->abs_invoice_total_amount, $this->bigClass)->class('border-l border-level3 pl-4'),
                )->class('text-right'),
            )->class('space-x-8 mb-4 p-6 bg-white rounded-2xl'),
            $this->stepBox(
                _Rows(
                    $this->stepTitle('finance-approval'),
                    $this->model->approvedBy ?
                        $this->model->approvalEls() :
                        _Flex(
                            _Html(__('finance-invoice-created-at') . ' :')->class('font-bold'),
                            _HtmlDate($this->model->created_at)->class('ml-4')
                        )
                ),
                _FlexEnd4(
                    !$this->model->canApprove() ? null :
                        _Button('finance-approve-draft')
                        ->when(
                            $this->model->hasMissingInfoToApprove(),
                            fn ($e) =>
                            $e->selfGet('getMissingInfoToApproveModal')->inModal()
                        )
                        ->when(
                            !$this->model->hasMissingInfoToApprove(),
                            fn ($e) =>
                            $e->selfPost('approveInvoice', ['id' => $this->model->id])
                                ->inAlert()->refresh()
                        ),
                )->class('text-right')
            )->class('mb-4 p-6 bg-white rounded-2xl'),
            // Sending is refused on drafts by the service, and a credit note needs its own
            // template rather than the invoice one, whose body asks the customer to pay.
            ($this->model->is_draft || $this->model->isRefund()) ? null : $this->stepBox(
                _Rows(
                    $this->stepTitle('finance.send'),
                    $this->model->sentEls(),
                ),
                _FlexEnd4(
                    // !$this->model->isLate() ? null :
                    //     _Button('finance-late-interests')->icon(_Sax('add', 20))->class('!bg-danger text-white'),

                    _Link('finance-send-invoice')->button()
                        ->selfGet('getSendInvoiceModal', ['id' => $this->model->id])
                        ->inModal(),
                )
            )->class('mb-4 p-6 bg-white rounded-2xl'),

            _Rows(
                $this->model->canApprove() ? null : $this->stepBox(
                    $this->model->isRefund() ?

                        _Rows(
                            $this->stepTitle('finance-apply-credit'),
                            $this->amountDue()
                        ) :

                        _Rows(
                            $this->stepTitle('finance-get-paid'),
                            _Flex4(
                                $this->amountDueDate(),
                                $this->lastPaymentWithDate(),
                            )
                        ),
                    $this->moneyBoxActions(),
                ),
                _Rows(
                    new InvoicePaymentsTable([
                        'invoice_id' => $this->model->id,
                    ]),
                )->class('px-6'),
            )->class('p-6 !px-0 bg-white mb-4 rounded-2xl'),

            !$this->model->installmentsPeriods->count() ? null : _Rows(
                _TitleMini('finance-payment-period-installments')->class('uppercase text-greenmain opacity-70'),
                _Rows(
                    new PaymentInstallmentPeriodsTable([
                        'invoice_id' => $this->model->id,
                    ]),
                )->class('mb-4 bg-white'),
            ),

            _Rows(
                _TitleMini($this->model->isRefund() ? 'finance-credit-note-details' : 'finance-invoice-details')->class('uppercase mb-2 mt-4 text-greenmain opacity-70'),
                new InvoiceDetailsTable([
                    'invoice_id' => $this->model->id,
                ]),
            ),


            // _TitleMini('finance-journal-transactions')->class('uppercase mb-2 text-greenmain opacity-70'),
            // (new TransactionsMiniTable([
            // 	'invoice_id' => $this->model->id,
            // ]))->class('p-6 bg-white rounded-2xl mb-6'),

            // !$this->model->notes ? null : _Rows(
            // 	_TitleMini('finance-notes')->class('uppercase mb-2'),
            // 	_Html($this->model->notes)->class('p-6 bg-white rounded-2xl mb-6'),
            // ),


            // $this->model->attachedFilesBox(),
        ];
    }

    public function approveInvoice($id)
    {
        InvoiceService::approveInvoice(new ApproveInvoiceDto([
            'invoice_id' => $id,
        ]));

        return __($this->model->isRefund() ? 'finance-credit-note-approved' : 'finance-invoice-approved');
    }

    public function getSendInvoiceModal($id)
    {
        return new SendInvoiceModal($id);
    }

    public function getApplyPaymentToInvoiceModal()
    {
        return new PaymentForm(null, [
            'customer_id' => $this->model->customer_id,
            'invoice_id' => $this->model->id,
        ]);
    }

    /**
     * The two things you can do with a credit, or the two with an invoice — always in the
     * same slot, next to the amount they act on.
     */
    protected function moneyBoxActions()
    {
        if ($this->model->isRefund()) {
            if (!$this->model->abs_invoice_due_amount->greaterThan(0)) {
                return null;
            }

            return _FlexEnd4(
                _Link('finance-apply-to-an-invoice')->outlined()
                    ->selfGet('getApplyCreditModal')->inModal(),
                _Link('finance-refund-to-customer')->button()
                    ->selfGet('getRefundCreditModal')->inModal(),
            );
        }

        if (!$this->model->canBePaid()) {
            return null;
        }

        return _FlexEnd4(
            !$this->hasApplicableCredit() ? null : _Link('finance-apply-credit')->outlined()
                ->selfGet('getApplyCreditToInvoiceModal')->inModal(),
            _Link('finance-record-payment')->outlined()
                ->selfUpdate('getApplyPaymentToInvoiceModal')->inModal(),
        );
    }

    /** Spending this credit: the modal picks which invoices it goes to. */
    public function getApplyCreditModal()
    {
        return new ApplyPaymentToInvoiceModal(null, [
            'applicable_key' => MorphablesEnum::CREDIT->value . '|' . $this->model->id,
            'refresh_id' => $this->id,
        ]);
    }

    /** The mirror: this invoice is the target, the operator picks the credit. */
    public function getApplyCreditToInvoiceModal()
    {
        return new ApplyPaymentToInvoiceModal(null, [
            'customer_id' => $this->model->customer_id,
            'invoice_id' => $this->model->id,
            'refresh_id' => $this->id,
            'crediting' => true,
        ]);
    }

    public function getRefundCreditModal()
    {
        return new RefundCreditModal($this->model->id);
    }

    public function getCreditNoteModal()
    {
        return new CreditNoteForm(null, ['invoice_id' => $this->model->id]);
    }

    protected function canBeCredited(): bool
    {
        return !$this->model->is_draft && !$this->model->isRefund();
    }

    protected function hasApplicableCredit(): bool
    {
        return Credit::applicable($this->model->customer_id)->exists();
    }

    /** Cross-link a credit note and the invoice it corrects, both ways. */
    protected function relatedDocumentLink()
    {
        if ($this->model->creditedInvoice) {
            return _Link(__('finance-with-values-corrects-invoice', [
                'reference' => $this->model->creditedInvoice->invoice_reference,
            ]))->href('invoices.show', ['id' => $this->model->credited_invoice_id])
                ->class('text-sm text-level1 underline');
        }

        $credits = $this->model->creditNotes;

        if (!$credits->count()) {
            return null;
        }

        return _Html(__('finance-with-values-credited-by', [
            'references' => $credits->pluck('invoice_reference')->implode(', '),
        ]))->class('text-sm text-level1');
    }

    public function getMissingInfoToApproveModal()
    {
        return new SelectMissingInfoInvoice($this->model->id);
    }

    public function getPaymentEntryForm()
    {
        return new PaymentEntryForm([
            'type' => 'invoice',
            'id' => $this->model->id,
        ]);
    }

    protected function stepBox()
    {
        return _FlexBetween(func_get_args())->class('dashboard-card px-8 pb-8 pt-6');
    }

    protected function stepTitle($label)
    {
        return _Html($label)->class($this->bigClass)->class('pb-4 text-greenmain');
    }

    protected function amountDueDate()
    {
        return _FlexEnd4(
            $this->amountDue(),
            _MiniLabelDate('finance-due-date', $this->model->invoice_due_date, $this->bigClass)->class('border-l border-gray-200 pl-4'),
        );
    }

    protected function amountDue()
    {
        return _MiniLabelFinanceCcy(
            $this->model->isRefund() ? 'finance-credit-left' : 'finance-amount-due',
            $this->model->abs_invoice_due_amount,
            $this->bigClass,
        );
    }

    protected function lastPaymentWithDate()
    {
        $lastPayment = $this->model->lastPayment;

        if (!$lastPayment) {
            return;
        }

        return _FlexEnd(
            _Rows(
                _MiniLabel('finance-last-payment'),
                _Flex4(
                    _HtmlDate(carbon($lastPayment->transacted_at))->class($this->bigClass),
                    _FinanceCurrency($lastPayment->amount)->class($this->bigClass)
                ),
            )->class('border-l border-level4 pl-4')
        );
    }
}

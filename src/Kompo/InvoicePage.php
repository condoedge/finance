<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Facades\InvoiceModel;
use Condoedge\Finance\Facades\InvoiceService;
use Condoedge\Finance\Kompo\PaymentTerms\PaymentInstallmentPeriodsTable;
use Condoedge\Finance\Models\Dto\Invoices\ApproveInvoiceDto;
use Kompo\Form;

class InvoicePage extends Form
{
    public $model = InvoiceModel::class;

    protected $bigClass = 'text-xl font-medium text-level1';

    public $id = 'charge-stage-page'; //shared with bill stage form

    public function render()
    {
        return [
            _FlexBetween(
                _Breadcrumbs(
                    _Link('finance-all-receivables')->href('invoices.list'),
                    _Html($this->model->invoice_reference),
                ),
                _FlexEnd4(
                    _Dropdown('finance-actions')->rIcon('icon-down')->button()
                        ->submenu(
                            _DropdownLink('finance-create-another-invoice')
                                ->href('finance.invoice-form'),
                        )->alignRight(),
                    _Link('finance-edit-invoice')->outlined()
                        ->href('invoices.form', ['id' => $this->model->id]),
                )
            )->class('mb-12'),
            _FlexBetween(
                _FlexEnd(
                    _Rows(
                        _MiniLabel('finance-status'),
                        $this->model->invoice_status_id->pill(),
                    ),
                    _Rows(
                        _MiniLabel('finance-client'),
                        _Html($this->model->customer_label)->class($this->bigClass),
                    )->class('border-l border-level4 pl-4'),
                )->class('space-x-8'),
                _FlexEnd4(
                    _MiniLabelDate('finance-invoice-date', $this->model->invoice_date, $this->bigClass),
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
            // $this->model->canApprove() ? null : $this->stepBox(
            // 	_Rows(
            // 		$this->stepTitle('finance.send'),
            // 		$this->model->sentEls(),
            // 	),
            // 	_FlexEnd4(
            // 		!$this->model->isLate() ? null :
            // 			_Button('finance-late-interests')->icon(_Sax('add',20))->class('!bg-danger text-white')
            // 				->inModal(),
            // 		_Link('finance-send-invoice')->outlined()
            // 			->selfPost('getSendingModal')->inModal()
            // 	)
            // )->class('mb-4 p-6 bg-white rounded-2xl'),
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
                !$this->model->canBePaid() ? null : _FlexEnd(
                    _Link('finance-record-payment')
                        ->outlined()
                        ->selfUpdate('getApplyPaymentToInvoiceModal')->inModal()
                )
            )->class('mb-4 p-6 bg-white rounded-2xl'),

            _Rows(
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

        return __('finance-invoice-approved');
    }

    public function getApplyPaymentToInvoiceModal()
    {
        return new PaymentForm([
            'customer_id' => $this->model->customer_id,
            'invoice_id' => $this->model->id,
        ]);
    }

    public function getSendingModal()
    {
        return new ContributionSendingSingleModal($this->model->id);
    }

    public function getMissingInfoToApproveModal()
    {
        return new SelectMissingInfoInvoice($this->model->id);
    }

    public function getLateInterestModal()
    {
        return new LateInterestModal($this->model->id);
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
        return _MiniLabelFinanceCcy('finance-amount-due', $this->model->abs_invoice_due_amount, $this->bigClass);
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

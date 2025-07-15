<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Facades\InvoiceService;
use Condoedge\Finance\Facades\PaymentMethodEnum;
use Condoedge\Finance\Kompo\Common\Modal;
use Condoedge\Finance\Models\Dto\Invoices\ApproveInvoiceDto;
use Condoedge\Finance\Models\Invoice;
use Condoedge\Finance\Models\PaymentTerm;

class SelectMissingInfoInvoice extends Modal
{
    protected $_Title = 'finance-select-missing-info-invoice';

    public $model = Invoice::class;

    public function handle()
    {
        InvoiceService::approveInvoice(new ApproveInvoiceDto([
            'invoice_id' => $this->model->id,
            'payment_method_id' => request('payment_method_id'),
            'payment_term_id' => request('payment_term_id'),
            'address' => parsePlaceFromRequest('address1'),
        ]));
    }

    public function body()
    {
        return _Rows(
            _Select('finance-payment-type')
                    ->default($this->model->payment_method_id)
                    ->name('payment_method_id')
                    ->options(PaymentMethodEnum::optionsWithLabels()),
            _Select('finance-payment-term')
                ->default($this->model->payment_term_id)
                ->name('payment_term_id')
                ->options(PaymentTerm::pluck('term_name', 'id')->toArray()),
            _CanadianPlace()
                ->default($this->model->address),
            _SubmitButton('finance-save-and-approve')
                ->closeModal()
                ->alert('finance-invoice-approved'),
        );
    }
}

<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Facades\InvoiceService;
use Condoedge\Finance\Facades\PaymentMethodEnum;
use Condoedge\Finance\Kompo\Common\Modal;
use Condoedge\Finance\Models\Dto\Invoices\ApproveInvoiceDto;
use Condoedge\Finance\Models\Dto\Invoices\UpdateInvoiceDto;
use Condoedge\Finance\Models\Invoice;
use Condoedge\Finance\Models\PaymentTerm;

class SelectMissingInfoInvoice extends Modal
{
    protected $_Title = 'translate.select-missing-info-invoice';

    public $model = Invoice::class;

    public function handle()
    {
        InvoiceService::updateInvoice(
            new UpdateInvoiceDto([
                'id' => $this->model->id,
                'payment_method_id' => request('payment_method_id'),
                'payment_term_id' => request('payment_term_id'),
            ])
        );

        InvoiceService::approveInvoice(new ApproveInvoiceDto([
            'invoice_id' => $this->model->id,
        ]));
    }

    public function body()
    {
        return _Rows(
            _Select('finance-payment-type')
                    ->name('payment_method_id')
                    ->options(PaymentMethodEnum::optionsWithLabels()),
            _Select('finance-payment-term')
                ->name('payment_term_id')
                ->options(PaymentTerm::pluck('term_name', 'id')->toArray()),
            _SubmitButton('translate.finance-save-and-approve')
        );
    }
}

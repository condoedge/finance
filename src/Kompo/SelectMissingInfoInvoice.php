<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Facades\InvoiceService;
use Condoedge\Finance\Kompo\Common\Modal;
use Condoedge\Finance\Models\Dto\Invoices\ApproveInvoiceDto;
use Condoedge\Finance\Models\Invoice;

class SelectMissingInfoInvoice extends Modal
{
    protected $_Title = 'finance-select-missing-info-invoice';

    public $model = Invoice::class;

    public function handle()
    {
        InvoiceService::approveInvoice(new ApproveInvoiceDto([
            'invoice_id' => $this->model->id,
            'address' => parsePlaceFromRequest('address1'),
        ]));
    }

    public function body()
    {
        return _Rows(
            $this->model->address ? null : _CanadianPlace()
                ->default($this->model->address)
                ->class('place-input-without-visual'),
            _SubmitButton('finance-save-and-approve')
                ->closeModal()
                ->refresh('invoice-page')
                ->alert('finance-invoice-approved'),
        );
    }
}

<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Facades\InvoiceModel;
use Condoedge\Finance\Facades\InvoiceService;
use Condoedge\Finance\Kompo\Common\Modal;
use Condoedge\Finance\Models\Dto\Invoices\ApproveInvoiceDto;

class SelectMissingInfoInvoice extends Modal
{
    protected $_Title = 'finance-select-missing-info-invoice';

    public $model = InvoiceModel::class;

    public function handle()
    {
        InvoiceService::approveInvoice(new ApproveInvoiceDto([
            'invoice_id' => $this->model->id,
        ]));
    }

    public function body()
    {
        return _Rows(
            _SubmitButton('finance-save-and-approve')
                ->closeModal()
                ->refresh('invoice-page')
                ->alert('finance-invoice-approved'),
        );
    }
}

<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Facades\InvoiceModel;
use Condoedge\Finance\Facades\InvoiceService;
use Condoedge\Finance\Kompo\Common\Modal;

class SendInvoiceModal extends Modal
{
    protected $_Title = 'finance-send-invoice';
    public $model = InvoiceModel::class;

    public function handle()
    {
        // The address was collected then dropped; InvoiceSent::getCommunicables()
        // returns nothing without it, so no invoice email has ever gone out.
        InvoiceService::sendInvoice($this->model->id, request('email'));
    }

    public function body()
    {
        return _Rows(
            _Input('finance-email')->name('email')
                ->default($this->model->mainCustomer?->email),

            _Flex(
                _ButtonOutlined('finance-cancel')->closeModal()->class('flex-1'),
                _SubmitButton()->alert('finance-invoice-sent')->closeModal()
                    ->refresh('invoice-page')->class('flex-1'),
            )->class('mt-4 gap-4'),
        );
    }
}
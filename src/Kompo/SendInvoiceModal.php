<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Facades\InvoiceModel;
use Condoedge\Finance\Facades\InvoiceService;
use Condoedge\Finance\Kompo\Common\Modal;

class SendInvoiceModal extends Modal
{
    protected $_Title = 'translate.finance-send-invoice';
    public $model = InvoiceModel::class;

    public function handle()
    {
        InvoiceService::sendInvoice($this->model->id);
    }

    public function body()
    {
        return _Rows(
            _Input('translate.finance-invoice-email')->name('email')
                ->default($this->model->mainCustomer?->email),

            _Flex(
                _ButtonOutlined('translate.finance-cancel')->closeModal()->class('flex-1'),
                _SubmitButton()->alert('finance-invoice-sent')->closeModal()
                    ->refresh('invoice-page')->class('flex-1'),
            )->class('mt-4 gap-4'),
        );
    }
}
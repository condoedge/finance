<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Facades\InvoiceModel;
use Condoedge\Utils\Kompo\Common\Form;

class InvoiceInfoModal extends Form
{
    public $class = 'overflow-y-auto mini-scroll max-w-lg';
    public $style = 'max-height: 95vh; width: 98vw;';

    public $model = InvoiceModel::class;

    protected $team;

    public function created()
    {
        $this->team = $this->model->team;
    }

    public function render()
    {
        return _Rows(
            _FlexEnd(
                $this->model->invoice_status_id->pill(),
            ),
            _Rows(
                _Img('images/logo-green.png')->class('w-28 h-28 mx-auto mb-4 '),
                _TitleModal(__('finance.invoice-number', ['number' => $this->model->invoice_reference]))->class('text-center text-black'),
                _Html(__('finance.issued-date', ['date' => $this->model->invoice_date->format('Y-m-d')]))->class('text-level1 mb-3'),
                _FinanceCurrency($this->model->invoice_total_amount)->class('text-3xl font-bold mb-4'),
                _FlexCenter(
                    _ButtonOutlined('finance.send-receipt')->class('!py-1')->icon('receipt'),
                    _ButtonOutlined('finance.send-invoice')->class('!py-1')->icon('receipt'),
                )->class('gap-4'),
            )->class('text-center border-b border-gray-200 pb-4 mb-4'),
            _Rows(
                _Rows(
                    _FlexBetween(
                        _Html('finance.from')->class('font-semibold text-black'),
                        _Link('finance.contact'),
                    ),
                    _Rows(
                        _Html($this->team->team_name),
                        _TextSm($this->team->getFirstValidAddressLabel()),
                    )
                )->class('text-level1'),
                _Rows(
                    _Html('finance.to')->class('font-semibold text-black'),
                    _Rows(
                        _Html($this->model->customer->name),
                        // _TextSm($this->model->customer->getFirstValidAddressLabel()),
                    )
                )->class('text-level1'),
            )->class('gap-3 mb-6'),
            _Tabs(
                _Tab(
                    _CardLevel4(
                        _FlexBetween(
                            _Html('finance.due-date'),
                            _Html($this->model->invoice_due_date?->format('Y-m-d') ?: 'finance-to-be-set')->class('font-semibold'),
                        ),
                        _FlexBetween(
                            _Html('finance.term'),
                            _Html($this->model->paymentTerm?->term_name ?: 'finance-to-be-selected')->class('font-semibold'),
                        )->class('mb-4'),
                        _FlexBetween(
                            _Html('finance.sub-total'),
                            _FinanceCurrency($this->model->invoice_amount_before_taxes)->class('font-semibold'),
                        ),
                        _FlexBetween(
                            _Html('finance.taxes'),
                            _FinanceCurrency($this->model->invoice_tax_amount)->class('font-semibold'),
                        ),
                        _FlexBetween(
                            _Html('finance.total'),
                            _FinanceCurrency($this->model->invoice_total_amount)->class('font-semibold'),
                        )->class('pb-3 mb-2 border-b border-gray-300'),
                        _FlexBetween(
                            _Html('finance.balance'),
                            _FinanceCurrency($this->model->invoice_due_amount)->class('font-semibold'),
                        ),
                    )->class('gap-1 p-6'),
                )->label('finance.summary'),
                _Tab(
                    _CardLevel4(
                        new InvoiceDetailQuery([
                            'invoice_id' => $this->model->id,
                        ]),
                    )->class('p-6'),
                    _FlexEnd(
                        _FlexBetween(
                            _Html('finance.total'),
                            _FinanceCurrency($this->model->invoice_total_amount)->class('font-semibold'),
                        )->class('gap-4'),
                    )->class('px-6'),
                )->label('finance.details'),
                _Tab(
                    _CardLevel4(
                        _LabelWithIcon(
                            SAX_ICON_CALENDAR,
                            _Html($this->model->paymentTerm?->term_name ?: 'finance-to-be-selected')->class('font-semibold'),
                        ),
                        _LabelWithIcon(
                            'wallet',
                            _Html($this->model->payment_method?->label() ?: 'finance-to-be-selected')->class('font-semibold'),
                        )->class('mb-4'),
                        // $this->model->paymentTerm?->preview($this->model),
                    )->class('p-6 gap-1'),
                )->label('finance.payments'),
            )->class('mb-4'),
            _Button('finance.pay-invoice')->selfGet('getInvoicePayModal')->inModal()->class('mb-4'),
        )->class('p-6');
    }

    public function getInvoicePayModal()
    {
        return new InvoicePayModal($this->model->id);
    }
}

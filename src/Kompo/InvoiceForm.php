<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Facades\InvoiceModel;
use Condoedge\Finance\Facades\InvoiceTypeEnum;
use Condoedge\Finance\Facades\PaymentMethodEnum;
use Condoedge\Finance\Models\Dto\Invoices\CreateInvoiceDto;
use Condoedge\Finance\Models\Dto\Invoices\UpdateInvoiceDto;
use Condoedge\Finance\Models\Invoice;
use Condoedge\Finance\Models\PaymentInstallmentEnum;
use Condoedge\Finance\Services\Invoice\InvoiceServiceInterface;
use Condoedge\Utils\Kompo\Common\Form;

class InvoiceForm extends Form
{
    use \Condoedge\Finance\Kompo\MorphManyChargeablesSelect;
    public const ID = 'invoice-form';
    public $id = self::ID;

    /**
     * @var Invoice $model
     */
    public $model = InvoiceModel::class;

    protected $customerTypePanelId = 'customer-type-panel';

    protected $team;

    public function created()
    {
        $this->team = currentTeam();
    }

    public function handle(InvoiceServiceInterface $invoiceService)
    {
        $invoiceData = parseDataWithMultiForm('invoiceDetails');

        $dtoInvoiceData = $this->model->id ?
            new UpdateInvoiceDto(['id' => $this->model->id, ...$invoiceData]) :
            new CreateInvoiceDto($invoiceData);

        $this->model($invoiceService->upsertInvoice($dtoInvoiceData));

        return $this->response();
    }

    public function response()
    {
        return redirect()->route('invoices.show', ['id' => $this->model->id]);
    }

    public function render()
    {
        return [
            _FlexBetween(
                _Breadcrumbs(
                    _BackLink('finance-all-receivables')->href('invoices.list'),
                    _Html('finance-edit'),
                ),
                _FlexEnd4(
                    $this->model->id ? _DeleteLink('finance-delete')->outlined()->byKey($this->model)->redirect('invoices.table') : null,
                    _SubmitButton('finance-save'),
                )
            )->class('mb-6'),

            _Columns(
                $this->model->id ? null : _Select('finance-invoice-type')
                    ->name('invoice_type_id')
                    ->options(InvoiceTypeEnum::optionsWithLabels()),
                $this->model->id ? null : _Flex(
                    _Rows(
                        new SelectCustomer(null, [
                            'team_id' => $this->team?->id,
                            'default_id' => $this->model->customer_id,
                        ]),
                    )->class('[&>form]:flex-1'),
                    _Rows(
                        _Html('&nbsp;'),
                        _Button()->icon('plus')->selfGet('getCustomerModal')->inModal(),
                    ),
                )->class('gap-3'),
                _Date('finance-invoice-date')->name('invoice_date')->default(date('Y-m-d')),
            ),

            _Columns(
                _Select('finance-payment-type')
                    ->name('payment_method_id')
                    ->options(PaymentMethodEnum::optionsWithLabels()),
                _Select('finance-payment-installment')
                    ->name('payment_installment_id')
                    ->options(PaymentInstallmentEnum::optionsWithLabels()),
                _Date('finance-due-date')->name('invoice_due_date')->default(date('Y-m-d')),
            ),

            _MultiForm()->noLabel()->name('invoiceDetails')
                ->formClass(InvoiceDetailForm::class, [
                    'team_id' => $this->team->id,
                    'invoice_id' => $this->model->id,
                ])
                ->asTable([
                    __('finance-product-service'),
                    __('finance-account'),
                    _FlexBetween(
                        _Flex(
                            _Th('finance-quantity')->class('w-28'),
                            _Th('finance-price')->class('w-28'),
                        )->class('space-x-4'),
                        _Th('finance-total')->class('text-right'),
                    )->class('text-sm font-medium'),
                ])->addLabel(
                    $this->getChargeablesSelect(),
                )
                ->class('mb-6 bg-white rounded-2xl')
                ->id('finance-items'),

            _FlexEnd(
                _TotalFinanceCurrencyCols("title_to_replace", 'id_to_replace', 0, false)->class('hidden')->id('template_currency_format_cols'),
                _Rows(
                    _TitleMini('finance-invoice-total')->class('mb-2'),
                    _CardWhiteP4(
                        _TotalFinanceCurrencyCols(__('finance-subtotal'), 'finance-subtotal', $this->model->invoice_amount_before_taxes, false),
                        _Rows(
                            $this->model->getVisualTaxesGrouped()->map(
                                fn ($amount, $name) => _TotalFinanceCurrencyCols($name, 'finance-tax', $amount, false)
                            )->values(),
                        )->id('tax-summary'),
                        _TotalFinanceCurrencyCols(__('finance-total'), 'finance-total', $this->model->invoice_total_amount)->class('!font-bold text-xl'),
                        _TaxesInfoLink()->class('left-4 bottom-6'),
                    )->class('relative p-6 bg-white rounded-2xl'),
                    _FlexEnd(
                        _SubmitButton('finance-save'),
                    ),
                )->class('w-96'),
            ),
        ];
    }

    public function js()
    {
        return financeScriptFile();
    }

    public function getCustomerModal()
    {
        return new CustomerForm(null, [
            'refresh_id' => 'select-customer',
        ]);
    }

    public function getTaxesInfoModal()
    {
        return new TaxesInfoModal();
    }

    public function rules()
    {
        return [
            // 'invoice_due_date' => 'required',
            // 'invoice_date' => 'required',
            // 'payment_method_id' => 'required',
            // 'invoice_type_id' => 'required',
        ];
    }
}

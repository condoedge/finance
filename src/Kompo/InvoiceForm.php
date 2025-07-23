<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Facades\InvoiceModel;
use Condoedge\Finance\Facades\InvoiceTypeEnum;
use Condoedge\Finance\Facades\PaymentMethodEnum;
use Condoedge\Finance\Models\Customer;
use Condoedge\Finance\Models\Dto\Invoices\CreateInvoiceDto;
use Condoedge\Finance\Models\Dto\Invoices\UpdateInvoiceDto;
use Condoedge\Finance\Models\Invoice;
use Condoedge\Finance\Services\Invoice\InvoiceServiceInterface;
use Condoedge\Utils\Kompo\Common\Form;

class InvoiceForm extends Form
{
    use \Condoedge\Finance\Kompo\MorphManyChargeablesSelect;
    use \Condoedge\Finance\Kompo\PaymentTerms\TermSelectorTrait;

    public const ID = 'invoice-form';
    public $id = self::ID;

    /**
     * @var Invoice $model
     */
    public $model = InvoiceModel::class;

    protected $customerTypePanelId = 'customer-type-panel';

    protected $team;
    protected $modalDesign;
    protected $refreshId;

    public function created()
    {
        $this->team = currentTeam();

        $this->modalDesign = $this->prop('modal_design');

        if ($this->modalDesign) {
            $this->class('p-8 overflow-y-auto mini-scroll');
            $this->style('max-height: 90vh;');
        }

        $this->refreshId = $this->prop('refresh_id');

        // In modals is not loading the js method so we need to run it manually
        $this->onLoad(fn ($e) => $e->run('() => {
            '.financeScriptFile() . '
        }'));
    }

    public function handle(InvoiceServiceInterface $invoiceService)
    {
        $invoiceData = parseDataWithMultiForm('invoiceDetails');
        $invoiceData = $this->parsePossiblePaymentMethods($invoiceData);

        $dtoInvoiceData = $this->model->id ?
            new UpdateInvoiceDto(['id' => $this->model->id, ...$invoiceData]) :
            new CreateInvoiceDto($invoiceData);

        $this->model($invoiceService->upsertInvoice($dtoInvoiceData));

        if ($this->modalDesign) {
            return null;
        }

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
                $this->modalDesign ? _Html('finance-edit')->class('text-2xl font-semibold') : _Breadcrumbs(
                    _BackLink('finance-all-receivables')->href('invoices.list'),
                    _Html('finance-edit'),
                ),
                $this->modalDesign ? null : _FlexEnd4(
                    $this->model->id ? _DeleteLink('finance-delete')->outlined()->byKey($this->model)->redirect('invoices.table') : null,
                    _SubmitButton('finance-save'),
                )
            )->class('mb-6 gap-8'),

            _Columns(
                $this->model->id ? null : _Select('finance-transaction-type')
                    ->name('invoice_type_id')
                    ->options(InvoiceTypeEnum::optionsWithLabels()),
                $this->model->id ? null : _Flex(
                    _Rows(
                        _Panel()->id('customer-after-save-info'),
                        _Select('finance-invoiced-to')->name('customer_id')->class('!mb-0 select-on-create')
                            ->searchOptions(2, 'searchCustomers'),
                    )->class('[&>form]:flex-1'),
                    _Rows(
                        _Html('&nbsp;'),
                        _Button()->icon('plus')->selfGet('getCustomerModal')->inModal(),
                    ),
                )->class('gap-3'),
                _Date('finance-invoice-date')->name('invoice_date')->default(date('Y-m-d')),
            ),

            _Columns(
                _MultiSelect('finance-payment-types')
                    ->name('possible_payment_methods')
                    ->options(PaymentMethodEnum::optionsWithLabels()),
                $this->getPaymentTermsSelector($this->model->paymentTerm?->term_type),
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
                    )->class('relative p-6 bg-white rounded-2xl'),
                    _FlexEnd(
                        _SubmitButton('finance-save')
                            ->when($this->modalDesign, fn ($e) => $e->closeModal())
                            ->when($this->refreshId, fn ($e) => $e->refresh($this->refreshId)),
                    ),
                )->class('w-96'),
            ),
        ];
    }

    public function searchCustomers($searchTerm)
    {
        return Customer::where('team_id', $this->team->id ?? currentTeamId())
            ->where('name', 'like', wildcardSpace($searchTerm))
            ->orderBy('name')
            ->get()
            ->unique(fn ($c) => $c->customable_type . '_' . $c->customable_id . '_' .$c->name)
            ->mapWithKeys(function ($customer) {
                return [$customer->id => '<span data-id ="' . $customer->id . '">' . $customer->name . '</span>'];
            });
    }

    protected function parsePossiblePaymentMethods($requestData = null)
    {
        $requestData = $requestData ?: request()->all();

        if (!isset($requestData['possible_payment_terms'])) {
            return $requestData;
        }

        $requestData['possible_payment_terms'] = !is_array($requestData['possible_payment_terms']) ?
            [$requestData['possible_payment_terms']] :
            $requestData['possible_payment_terms'];

        return $requestData;
    }

    public function js()
    {
        return financeScriptFile();
    }

    public function getCustomerModal()
    {
        return new CustomerForm();
    }

    protected function getDefaultPaymentTerms()
    {
        return $this->model->possible_payment_terms ?? [];
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

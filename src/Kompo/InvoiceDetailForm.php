<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Facades\InvoiceDetailModel;
use Condoedge\Finance\Facades\ProductModel;
use Kompo\Form;

class InvoiceDetailForm extends Form
{
    public $model = InvoiceDetailModel::class;
    public $class = 'align-top';
    protected $teamId;
    protected $invoiceId;
    protected $invoice;

    protected $productId;
    protected $product;

    protected $createProductsOnSave = false;

    public function created()
    {
        $this->teamId = $this->prop('team_id');

        $this->productId = $this->prop('product_id');
        $this->product = $this->productId > 0 ? ProductModel::find($this->productId) : null;

        $this->createProductsOnSave = $this->productId === -1;
    }

    public function render()
    {
        return [
            _Rows(
                _Hidden()->onLoad->run('calculateTotals'),
                _Hidden()->name('create_product_on_save')->default($this->createProductsOnSave ? 1 : 0),
                _Hidden()->name('product_id')->default($this->product->id ?? null),
                _Input()->placeholder('finance.new-item-name')->name('name')->class('w-72 !mb-2')
                    ->default($this->product?->product_name),
                _Input()->placeholder('finance.item-description')->name('description')->class('!mb-0')->style('width: 170%')
                    ->default($this->product?->product_description),
            ),

            _AccountsSelect(account: $this->model->revenueAccount()->first() ?? $this->product?->defaultRevenueAccount)
                ->name('revenue_natural_account_id', false)
                ->class('w-full !mb-0'),

            _Rows(
                _Flex(
                    _Input()->type('number')
                        ->name('quantity')
                        ->default(1)
                        ->class('w-28 !mb-0')
                        ->run('calculateTotals'),
                    _Input()->type('number')
                        ->name('unit_price', false)
                        ->default($this->model->unit_price?->toFloat() ?? $this->product?->getAmount()->toFloat() ?? 0)
                        ->class('w-28 !mb-0')
                        ->run('calculateTotals'),
                    _FinanceCurrency($this->model->extended_price)
                        ->class('item-total w-32 text-lg font-semibold text-level1 text-right'),
                )->class('gap-3'),
                _FlexBetween(
                    _Flex(
                        _TaxesSelect($this->model, 'taxesIds')
                            ->class('w-60 !mb-0 mt-2')
                            ->run('calculateTotals'),
                        _FlexEnd(
                            _Rows(
                                $this->model->invoiceTaxes()->get()->map(
                                    fn ($it) => _FinanceCurrency($this->model->extended_price->multiply($it->tax_rate))
                                )
                            )->class('w-32 item-taxes font-semibold text-level1 text-right')
                        )->class('relative'),
                    ),
                ),
            ),

            $this->deleteInvoiceDetail()
                ->class('text-xl text-gray-300')
                ->run('calculateTotals'),

        ];
    }

    protected function deleteInvoiceDetail()
    {
        return $this->model->id ?

            _DeleteLink()->byKey($this->model) :

            _Link()->icon('icon-trash')->emitDirect('deleted');
    }

    public function rules()
    {
        return [
            'quantity' => 'required',
            'unit_price' => 'required',
            'name' => 'sometimes|required',
            'revenue_natural_account_id' => 'required|exists:fin_segment_values,id',
        ];
    }
}

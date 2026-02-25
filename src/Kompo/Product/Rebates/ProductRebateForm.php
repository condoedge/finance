<?php

namespace Condoedge\Finance\Kompo\Product\Rebates;

use Condoedge\Finance\Facades\ProductService;
use Condoedge\Finance\Models\Dto\Products\CreateRebateDto;
use Condoedge\Finance\Models\Rebate;
use Condoedge\Finance\Models\RebateAmountTypeEnum;
use Condoedge\Finance\Services\Product\Rebates\RebateHandlerService;
use Kompo\Form;

class ProductRebateForm extends Form
{
    public $model = Rebate::class;

    protected $index;
    protected $productId;

    public function created()
    {
        $this->index = \Str::random(8);
    }

    public function handle()
    {
        $this->model->forceFill(request()->all());
        $this->model->amount_type = RebateAmountTypeEnum::from(request('amount_type')); // Force cast

        if ($this->productId) {
            ProductService::createRebate(new CreateRebateDto([
                'product_id' => $this->productId,
                'rebate_logic_type' => $this->model->rebate_logic_type,
                'rebate_logic_parameters' => $this->model->rebate_logic_parameters,
                'amount' => $this->model->amount,
                'amount_type' => $this->model->amount_type,
            ]));
        }

        return response()->kompoMulti([
            response()->addToQuery('product-rebate-list', _TableRow(
                _Rows(
                    _Hidden()->name("rebate[{$this->index}][rebate_logic_type]", false)->value($this->model->rebate_logic_type),
                    _Html($this->model->handler_label),
                ),
                _Rows(
                    _Html($this->model->handler_params_label),
                    _Rows(
                        collect($this->model->rebate_logic_parameters)->map(function ($value, $key) {
                            return _Hidden()->name("rebate[{$this->index}][rebate_logic_parameters][{$key}]", false)->value($value);
                        })
                    )
                ),
                _Rows(
                    _Hidden()->name("rebate[{$this->index}][amount]", false)->value($this->model->amount),
                    _Hidden()->name("rebate[{$this->index}][amount_type]", false)->value($this->model->amount_type->value),
                    _Html($this->model->visual_amount),
                ),
            )),
            response()->closeModal(),
        ]);
    }

    public function render()
    {
        $rebateService = app()->make(RebateHandlerService::class);

        return _Rows(
            _Select('translate.logic-on')->name('rebate_logic_type')->options($rebateService->getRebateHandlersWithLabels())
                ->selfGet('getRebateHandlerParamsFields')->inPanel('logic-params-modal'),

            _Panel(

            )->id('logic-params-modal'),

            _Html('translate.amount*')->class('text-sm font-semibold mb-1 cursor-default text-level1'),
            _Columns(
                _InputNumber()->name('amount')->class('mb-0')->required(),
                _ButtonGroup()->name('amount_type')
                    ->options([
                        RebateAmountTypeEnum::PERCENT->value => RebateAmountTypeEnum::PERCENT->getAmountSymbol(),
                        RebateAmountTypeEnum::AMOUNT->value => RebateAmountTypeEnum::AMOUNT->getAmountSymbol(),
                    ])->optionClass('text-2xl font-bold text-center px-4 py-2 h-12 cursor-pointer border-level1 border')
                    ->selectedClass('bg-level1 text-white', 'text-level1')
                    ->class('mb-0')
                    ->required(),
            )->class('mb-3'),

            _SubmitButton('translate.save'),
        )->class('p-8');
    }

    public function getRebateHandlerParamsFields($handlerKey)
    {
        if (!$handlerKey) {
            return null;
        }

        $rebateService = app()->make(RebateHandlerService::class);
        $handler = $rebateService->getRebateHandler($handlerKey);

        return $handler->getHandlerParamsFields();
    }

    public function rules()
    {
        return [
            'rebate_logic_type' => ['required', 'string'],
            'amount' => ['required', 'numeric'],
            'amount_type' => ['required', 'string'],
        ];
    }
}
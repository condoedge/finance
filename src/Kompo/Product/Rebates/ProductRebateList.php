<?php

namespace Condoedge\Finance\Kompo\Product\Rebates;

use Condoedge\Finance\Models\Rebate;
use Condoedge\Utils\Kompo\Common\Table;

class ProductRebateList extends Table
{
    public $id = 'product-rebate-list';

    public $class = 'p-6';

    protected $productId;
    protected $multiFormClass = ProductRebateForm::class;

    public function created()
    {
        $this->productId = $this->prop('product_id');
    }

    public function headers()
    {
        return [
            _Th('translate.logic-on'),
            _Th('translate.logic'),
            _Th('translate.amount'),
        ];
    }

    public function query()
    {
        return Rebate::where('product_id', $this->productId)->get();
    }

    public function render($rebate)
    {
        return _TableRow(
            _Html($rebate->handler_label),
            _Html($rebate->handler_params_label),
            _Html($rebate->visual_amount),
        )->selfGet('getRebateForm', [
            'id' => $rebate->id,
        ])->inModal();
    }

    public function bottom()
    {
        return _Rows(
            _Hidden()->name('product_id', false)->value($this->productId),
            _Button('translate.create-rebate')->outlined()->icon('plus')->class('mt-4')
                ->selfGet('getRebateForm')->inModal(),
        );
    }

    public function getRebateForm($id = null)
    {
        return new ($this->multiFormClass)($id, [
            'product_id' => $this->productId,
        ]);
    }

    /**
     * Build a table row with embedded hidden fields for form data collection.
     * Used by the multiFormClass to add unsaved rebates to the table via addToQuery,
     * so that nestedFields can collect the data when the parent form submits.
     */
    public static function buildFormRow(Rebate $rebate, string $index)
    {
        return _TableRow(
            _Rows(
                _Hidden()->name("rebate[{$index}][rebate_logic_type]", false)->value($rebate->rebate_logic_type),
                _Html($rebate->handler_label),
            ),
            _Rows(
                _Html($rebate->handler_params_label),
                _Rows(
                    collect($rebate->rebate_logic_parameters)->map(fn ($value, $key) =>
                        _Hidden()->name("rebate[{$index}][rebate_logic_parameters][{$key}]", false)->value($value)
                    )
                ),
            ),
            _Rows(
                _Hidden()->name("rebate[{$index}][amount]", false)->value($rebate->amount),
                _Hidden()->name("rebate[{$index}][amount_type]", false)->value($rebate->amount_type->value),
                _Html($rebate->visual_amount),
            ),
        );
    }
}
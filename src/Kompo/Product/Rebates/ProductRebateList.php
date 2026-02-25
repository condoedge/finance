<?php

namespace Condoedge\Finance\Kompo\Product\Rebates;

use Condoedge\Finance\Models\Rebate;
use Condoedge\Utils\Kompo\Common\Table;

class ProductRebateList extends Table
{
    public $id = 'product-rebate-list';

    public $class = 'p-6';

    protected $productId;
    
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
        return new ProductRebateForm($id, [
            'product_id' => $this->productId,
        ]);
    }
}
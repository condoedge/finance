<?php

namespace Condoedge\Finance\Models;

use Condoedge\Finance\Services\Product\Rebates\RebateHandlerService;
use Condoedge\Utils\Models\Model;

/**
 * @property int $id
 * @property int $product_id
 * @property string|null $name
 * @property float $amount
 * @property RebateAmountTypeEnum $amount_type
 * @property string $rebate_logic_type
 * @property array $rebate_logic_parameters
 */
class Rebate extends Model
{
    protected $table = 'fin_rebates';
    
    protected $cast = [
        'amount_type' => RebateAmountTypeEnum::class,
        'rebate_logic_parameters' => 'array',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    // CALCULATED FIELDS
    public function getVisualAmountAttribute(): string
    {
        return $this->amount_type->getVisualAmount($this);
    }

    public function getAmountOffForProduct(Product $product): float
    {
        return $this->amount_type->getAmountOff($this, $product->price);
    }

    public function getHandlerLabelAttribute(): string
    {
        $handler = app()->make(RebateHandlerService::class)->getRebateHandler($this->rebate_logic_type);
        
        return $handler->getHandlerLabel();
    }

    public function getHandlerParamsLabelAttribute(): string
    {
        $handler = app()->make(RebateHandlerService::class)->getRebateHandler($this->rebate_logic_type);
        return $handler->getHandlerParamsLabel($this->rebate_logic_parameters);
    }
}
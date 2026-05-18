<?php

namespace Condoedge\Finance\Services\Product\Rebates;

use Condoedge\Finance\Facades\ProductTypeEnum;
use Condoedge\Finance\Models\Product;
use Condoedge\Finance\Models\Rebate;

abstract class AbstractRebateHandler 
{
    protected $context;

    public function __construct($context = [])
    {
        $this->context = $context;
    }

    public function calculateRebate(Product $product, Rebate $rebate, $currentDiscount = 0)
    {
        if ($product->product_type == ProductTypeEnum::getEnumCase('REBATE')) {
            throw new \InvalidArgumentException("Rebate cannot be applied to a product of type REBATE");
        }

        $amountToWorkOn = $product->product_cost->abs() - $currentDiscount;

        // By default we just call the getAmountOff method of the RebateAmountTypeEnum, but we could have more complex logic with different rebate handlers
        return $rebate->amount_type->getAmountOff($rebate, $amountToWorkOn);
    }

    abstract function shouldApplyRebate(Product $product, Rebate $rebate): bool;

    abstract function getHandlerLabel(): string;

    abstract function getHandlerParamsFields();

    abstract function getHandlerParamsRules(): array;

    abstract function getHandlerParamsLabel($params): string;
}

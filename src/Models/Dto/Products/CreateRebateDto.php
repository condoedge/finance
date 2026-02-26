<?php

namespace Condoedge\Finance\Models\Dto\Products;

use Condoedge\Finance\Casts\SafeDecimalCast;
use Condoedge\Finance\Models\RebateAmountTypeEnum;
use Condoedge\Finance\Rule\SafeDecimalRule;
use Dedoc\Scramble\Support\Type\EnumCaseType;
use WendellAdriel\ValidatedDTO\Casting\ArrayCast;
use WendellAdriel\ValidatedDTO\Casting\EnumCast;
use WendellAdriel\ValidatedDTO\ValidatedDTO;

/**
 * Create Rebate DTO
 *
 * Used to create new rebates with associated product information.
 *
 * @property int $product_id ID of the product
 * @property string $rebate_logic_type Type of rebate logic
 * @property array $rebate_logic_parameters Parameters for the rebate logic
 * @property float $amount Amount of the rebate
 * @property string $amount_type Type of the rebate amount (e.g., percentage or fixed)
 * @property int|null $product_template_id Parent product template ID (if this is based on a template)
 * @property string|null $productable_type Morph type for related model
 * @property int|null $productable_id Morph ID for related model
 */
class CreateRebateDto extends ValidatedDTO
{

    /**
     * Validation rules for creating a product
     */
    public function rules(): array
    {
        return [
            'product_id' => 'required|integer|exists:fin_products,id',
            'rebate_logic_type' => 'required|string|max:255',
            'rebate_logic_parameters' => 'required|array',
            'amount' => [new SafeDecimalRule(true), 'required'],
            'amount_type' => 'required|integer|in:' . collect(RebateAmountTypeEnum::cases())->pluck('value')->join(','),
        ];
    }

    public function casts(): array
    {
        return [
            'amount' => new SafeDecimalCast(),
            'amount_type' => new EnumCast(RebateAmountTypeEnum::class),
        ];
    }

    public function defaults(): array
    {
        return [
            'rebate_logic_parameters' => [],
        ];
    }
}

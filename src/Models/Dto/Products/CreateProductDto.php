<?php

namespace Condoedge\Finance\Models\Dto\Products;

use Condoedge\Finance\Casts\SafeDecimal;
use Condoedge\Finance\Casts\SafeDecimalCast;
use Condoedge\Finance\Rule\SafeDecimalRule;
use WendellAdriel\ValidatedDTO\Casting\ArrayCast;
use WendellAdriel\ValidatedDTO\Casting\IntegerCast;
use WendellAdriel\ValidatedDTO\Casting\StringCast;
use WendellAdriel\ValidatedDTO\ValidatedDTO;

/**
 * Create Product DTO
 *
 * Used to create new products with associated tax and revenue account information.
 *
 * @property int $product_type Type of product (1 = Service, 2 = Physical)
 * @property string $product_name Name of the product
 * @property string|null $product_description Description of the product
 * @property float $product_cost_abs Base cost/price of the product
 * @property int|null $team_id Team this product belongs to
 * @property int $default_revenue_account_id Default revenue GL account for this product
 * @property array|null $taxes_ids Array of tax IDs that apply to this product
 * @property int|null $product_template_id Parent product template ID (if this is based on a template)
 * @property string|null $productable_type Morph type for related model
 * @property int|null $productable_id Morph ID for related model
 */
class CreateProductDto extends ValidatedDTO
{
    public int $product_type;
    public string $product_name;
    public ?string $product_description;
    public SafeDecimal $product_cost_abs;
    public ?int $team_id;
    public int $default_revenue_account_id;
    public ?array $taxes_ids;
    public ?int $product_template_id;
    public ?string $productable_type;
    public ?int $productable_id;

    public ?string $key = null;

    /**
     * Validation rules for creating a product
     */
    public function rules(): array
    {
        return [
            'product_type' => 'required|integer|in:1,5,10,15', // ProductTypeEnum values
            'product_name' => 'required|string|max:255',
            'product_description' => 'nullable|string|max:1000',
            'product_cost_abs' => [new SafeDecimalRule(true), 'required'],
            'team_id' => 'nullable|integer|exists:teams,id',
            'default_revenue_account_id' => 'required|integer|exists:fin_gl_accounts,id',
            'taxes_ids' => 'nullable|array',
            'taxes_ids.*' => 'integer|exists:fin_taxes,id',
            'product_template_id' => 'nullable|integer|exists:fin_products,id',
            'productable_type' => 'nullable|string|max:255',
            'productable_id' => 'nullable|integer',
            'key' => 'nullable|string|max:255',
        ];
    }

    public function casts(): array
    {
        return [
            'product_type' => new IntegerCast(),
            'product_name' => new StringCast(),
            'product_description' => new StringCast(),
            'product_cost_abs' => new SafeDecimalCast(),
            'team_id' => new IntegerCast(),
            'default_revenue_account_id' => new IntegerCast(),
            'taxes_ids' => new ArrayCast(),
            'product_template_id' => new IntegerCast(),
            'productable_type' => new StringCast(),
            'productable_id' => new IntegerCast(),
            'key' => new StringCast(),
        ];
    }

    public function defaults(): array
    {
        return [
            'product_description' => null,
            'product_cost_abs' => null,
            'team_id' => null,
            'taxes_ids' => [],
            'product_template_id' => null,
            'productable_type' => null,
            'productable_id' => null,
            'key' => null,
        ];
    }
}

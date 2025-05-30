<?php

namespace Condoedge\Finance\Models\Dto\Bills;

use WendellAdriel\ValidatedDTO\Casting\FloatCast;
use WendellAdriel\ValidatedDTO\Casting\IntegerCast;
use WendellAdriel\ValidatedDTO\Casting\StringCast;
use WendellAdriel\ValidatedDTO\Concerns\EmptyDefaults;
use WendellAdriel\ValidatedDTO\ValidatedDTO;

class CreateOrUpdateBillDetail extends ValidatedDTO
{
    use EmptyDefaults;

    /**
     * Send this field as null to create a new bill detail instead of updating it.
     * @var int|null
     */
    public ?int $id;

    public int $bill_id;
    public string $name;
    public ?string $description;
    public int $quantity;
    public float $unit_price;
    public int $expense_account_id;
    public ?int $product_id;
    public ?array $taxesIds;

    public function rules(): array
    {
        return [
            /**
             * Send this field as null to create a new bill detail instead of updating it.
             * @var integer|null
             * @example null
             */
            'id' => 'nullable|integer|exists:fin_bill_details,id',

            'bill_id' => 'required|integer|exists:fin_bills,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
            'quantity' => 'required|integer|min:1|max:2147483647',
            'unit_price' => 'required|numeric|gt:0|max:99999999999999.99999',
            'expense_account_id' => 'required|integer|exists:fin_accounts,id',
            'product_id' => 'nullable|integer|exists:fin_products,id',
            'taxesIds' => 'nullable|array',
            'taxesIds.*' => 'integer|exists:fin_taxes,id',
        ];
    }

    public function casts(): array
    {
        return [
            'bill_id' => new IntegerCast,
            'name' => new StringCast,
            'description' => new StringCast,
            'quantity' => new IntegerCast,
            'unit_price' => new FloatCast,
            'expense_account_id' => new IntegerCast,
            'product_id' => new IntegerCast,
        ];
    }
}

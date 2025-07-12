<?php

namespace Condoedge\Finance\Models\Dto\Invoices;

use Condoedge\Finance\Casts\SafeDecimal;
use Condoedge\Finance\Casts\SafeDecimalCast;
use WendellAdriel\ValidatedDTO\Casting\IntegerCast;
use WendellAdriel\ValidatedDTO\Casting\StringCast;
use WendellAdriel\ValidatedDTO\Concerns\EmptyDefaults;
use WendellAdriel\ValidatedDTO\ValidatedDTO;

class PayableLineDto extends ValidatedDTO
{
    use EmptyDefaults;

    public string $description;
    public string $sku;
    public SafeDecimal $price;
    public int $quantity;
    public SafeDecimal $amount;

    public function rules(): array
    {
        return [
            'description' => 'required|string|max:255',
            'sku' => 'required|string|max:100',
            'price' => 'required|numeric|min:0',
            'quantity' => 'required|integer|min:1',
            'amount' => 'required|numeric|min:0',
        ];
    }

    public function casts(): array
    {
        return [
            'description' => new StringCast(),
            'sku' => new StringCast(),
            'price' => new SafeDecimalCast(),
            'quantity' => new IntegerCast(),
            'amount' => new SafeDecimalCast(),
        ];
    }
}

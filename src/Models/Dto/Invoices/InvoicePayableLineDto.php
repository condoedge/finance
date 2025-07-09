<?php

namespace Condoedge\Finance\Models\Dto\Invoices;

use WendellAdriel\ValidatedDTO\Casting\FloatCast;
use WendellAdriel\ValidatedDTO\Casting\IntegerCast;
use WendellAdriel\ValidatedDTO\Casting\StringCast;
use WendellAdriel\ValidatedDTO\Concerns\EmptyDefaults;
use WendellAdriel\ValidatedDTO\ValidatedDTO;

class InvoicePayableLineDto extends ValidatedDTO
{
    use EmptyDefaults;

    public string $description;
    public string $sku;
    public float $price;
    public int $quantity;
    public float $amount;

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
            'price' => new FloatCast(),
            'quantity' => new IntegerCast(),
            'amount' => new FloatCast(),
        ];
    }
}

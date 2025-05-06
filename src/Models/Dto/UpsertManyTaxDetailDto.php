<?php

namespace Condoedge\Finance\Models\Dto;

use WendellAdriel\ValidatedDTO\Casting\ArrayCast;
use WendellAdriel\ValidatedDTO\Casting\IntegerCast;
use WendellAdriel\ValidatedDTO\Concerns\EmptyDefaults;
use WendellAdriel\ValidatedDTO\ValidatedDTO;

class UpsertManyTaxDetailDto extends ValidatedDTO
{
    use EmptyDefaults;

    public int $invoice_detail_id;

    public array $taxes_ids;

    public function rules(): array
    {
        return [
            'invoice_detail_id' => 'required|exists:fin_invoice_details,id',
            'taxes_ids' => 'array',
            'taxes_ids.*' => 'required|exists:fin_taxes,id',
        ];
    }

    public function casts(): array
    {
        return [
            'taxes_ids' => new ArrayCast,
            'invoice_detail_id' => new IntegerCast,
        ];
    }
}
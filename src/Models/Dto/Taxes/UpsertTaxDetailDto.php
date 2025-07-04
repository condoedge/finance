<?php

namespace Condoedge\Finance\Models\Dto\Taxes;

use WendellAdriel\ValidatedDTO\Casting\IntegerCast;
use WendellAdriel\ValidatedDTO\Concerns\EmptyDefaults;
use WendellAdriel\ValidatedDTO\ValidatedDTO;

class UpsertTaxDetailDto extends ValidatedDTO
{
    use EmptyDefaults;

    public int $invoice_detail_id;

    public int $tax_id;

    public function rules(): array
    {
        return [
            'invoice_detail_id' => 'required|exists:fin_invoice_details,id',
            'tax_id' => 'required|exists:fin_taxes,id',
        ];
    }

    public function casts(): array
    {
        return [
            'tax_id' => new IntegerCast(),
            'invoice_detail_id' => new IntegerCast(),
        ];
    }
}

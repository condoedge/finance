<?php

namespace Condoedge\Finance\Models\Dto\Bills;

use WendellAdriel\ValidatedDTO\Casting\IntegerCast;
use WendellAdriel\ValidatedDTO\ValidatedDTO;

class ApproveBillDto extends ValidatedDTO
{
    public int $bill_id;

    public function rules(): array
    {
        return [
            'bill_id' => 'required|integer|exists:fin_bills,id',
        ];
    }

    public function casts(): array
    {
        return [
            'bill_id' => new IntegerCast,
        ];
    }
}

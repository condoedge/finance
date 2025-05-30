<?php

namespace Condoedge\Finance\Models\Dto\Bills;

use WendellAdriel\ValidatedDTO\Casting\ArrayCast;
use WendellAdriel\ValidatedDTO\ValidatedDTO;

class ApproveManyBillsDto extends ValidatedDTO
{
    public array $bills_ids;

    public function rules(): array
    {
        return [
            'bills_ids' => 'required|array',
            'bills_ids.*' => 'integer|exists:fin_bills,id',
        ];
    }

    public function casts(): array
    {
        return [
            'bills_ids' => new ArrayCast,
        ];
    }
}

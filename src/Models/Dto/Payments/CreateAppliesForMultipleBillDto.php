<?php

namespace Condoedge\Finance\Models\Dto\Payments;

use Carbon\Carbon;
use WendellAdriel\ValidatedDTO\Casting\ArrayCast;
use WendellAdriel\ValidatedDTO\Casting\CarbonCast;
use WendellAdriel\ValidatedDTO\Casting\IntegerCast;
use WendellAdriel\ValidatedDTO\ValidatedDTO;

class CreateAppliesForMultipleBillDto extends ValidatedDTO
{
    public $applicable; // The applicable model instance
    public int $applicable_type;
    public Carbon $apply_date;
    public array $amounts_to_apply; // Array of ['id' => bill_id, 'amount_applied' => amount]

    public function rules(): array
    {
        return [
            'applicable' => 'required',
            'applicable_type' => 'required|integer',
            'apply_date' => 'required|date',
            'amounts_to_apply' => 'required|array',
            'amounts_to_apply.*.id' => 'required|integer|exists:fin_bills,id',
            'amounts_to_apply.*.amount_applied' => 'required|numeric|gt:0|max:99999999999999.99999',
        ];
    }

    public function casts(): array
    {
        return [
            'applicable_type' => new IntegerCast,
            'apply_date' => new CarbonCast,
            'amounts_to_apply' => new ArrayCast,
        ];
    }
}

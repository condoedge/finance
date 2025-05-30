<?php

namespace Condoedge\Finance\Models\Dto\Payments;

use Carbon\Carbon;
use WendellAdriel\ValidatedDTO\Casting\CarbonCast;
use WendellAdriel\ValidatedDTO\Casting\FloatCast;
use WendellAdriel\ValidatedDTO\Casting\IntegerCast;
use WendellAdriel\ValidatedDTO\ValidatedDTO;

class CreateApplyForBillDto extends ValidatedDTO
{
    public int $bill_id;
    public $applicable; // The applicable model instance
    public int $applicable_type;
    public Carbon $apply_date;
    public float $amount_applied;

    public function rules(): array
    {
        return [
            'bill_id' => 'required|integer|exists:fin_bills,id',
            'applicable' => 'required',
            'applicable_type' => 'required|integer',
            'apply_date' => 'required|date',
            'amount_applied' => 'required|numeric|gt:0|max:99999999999999.99999',
        ];
    }

    public function casts(): array
    {
        return [
            'bill_id' => new IntegerCast,
            'applicable_type' => new IntegerCast,
            'apply_date' => new CarbonCast,
            'amount_applied' => new FloatCast,
        ];
    }
}

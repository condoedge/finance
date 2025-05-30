<?php

namespace Condoedge\Finance\Models\Dto\Payments;

use Carbon\Carbon;
use WendellAdriel\ValidatedDTO\Casting\CarbonCast;
use WendellAdriel\ValidatedDTO\Casting\FloatCast;
use WendellAdriel\ValidatedDTO\Casting\IntegerCast;
use WendellAdriel\ValidatedDTO\ValidatedDTO;

class CreateVendorPaymentForBillDto extends ValidatedDTO
{
    public int $vendor_id;
    public int $bill_id; 
    public Carbon $payment_date;
    public float $amount;

    public function rules(): array
    {
        return [
            'vendor_id' => 'required|integer|exists:fin_vendors,id',
            'bill_id' => 'required|integer|exists:fin_bills,id',
            'payment_date' => 'required|date',
            'amount' => 'required|numeric|gt:0|max:99999999999999.99999',
        ];
    }

    public function casts(): array
    {
        return [
            'vendor_id' => new IntegerCast,
            'bill_id' => new IntegerCast,
            'payment_date' => new CarbonCast,
            'amount' => new FloatCast,
        ];
    }
}

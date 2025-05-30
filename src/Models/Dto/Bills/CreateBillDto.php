<?php

namespace Condoedge\Finance\Models\Dto\Bills;

use Carbon\Carbon;
use Condoedge\Finance\Facades\PaymentTypeEnum;
use WendellAdriel\ValidatedDTO\Casting\ArrayCast;
use WendellAdriel\ValidatedDTO\Casting\BooleanCast;
use WendellAdriel\ValidatedDTO\Casting\CarbonCast;
use WendellAdriel\ValidatedDTO\Casting\IntegerCast;
use WendellAdriel\ValidatedDTO\ValidatedDTO;

class CreateBillDto extends ValidatedDTO
{
    public int $vendor_id;
    public int $bill_type_id;
    public int $payment_type_id;
    public Carbon $bill_date;
    public Carbon $bill_due_date;

    public bool $is_draft;

    public array $billDetails;

    public function rules(): array
    {
        return [
            'vendor_id' => 'required|integer|exists:fin_vendors,id',
            'bill_type_id' => 'required|integer|exists:fin_bill_types,id',
            'payment_type_id' => 'required|integer|in:' . collect(PaymentTypeEnum::getEnumClass()::cases())->pluck('value')->implode(','),
            'bill_date' => 'required|date',
            'bill_due_date' => 'required|date|after_or_equal:bill_date',
            'is_draft' => 'boolean',

            'billDetails' => 'array',
            /**
             * Send this field as null to create a new bill detail instead of updating it.
             * @var integer|null
             * @example null
             */
            'billDetails.*.id' => 'nullable|integer|exists:fin_bill_details,id',
            'billDetails.*.name' => 'required|string|max:255',
            'billDetails.*.description' => 'nullable|string|max:255',
            'billDetails.*.quantity' => 'required|integer|min:1|max:2147483647',
            'billDetails.*.unit_price' => 'required|numeric|gt:0|max:99999999999999.99999',
            'billDetails.*.expense_account_id' => 'required|integer|exists:fin_accounts,id',
            'billDetails.*.taxesIds' => 'nullable|array',
            'billDetails.*.taxesIds.*' => 'integer|exists:fin_taxes,id',
        ];
    }

    public function casts(): array
    {
        return [
            'vendor_id' => new IntegerCast,
            'bill_type_id' => new IntegerCast,
            'payment_type_id' => new IntegerCast,
            'bill_date' => new CarbonCast,
            'bill_due_date' => new CarbonCast,
            'billDetails' => new ArrayCast,
            'is_draft' => new BooleanCast,
        ];
    }

    public function defaults(): array
    {
        return [
            'is_draft' => true,
        ];
    }
}

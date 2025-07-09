<?php

namespace Condoedge\Finance\Models\Dto\PaymentTerms;

use WendellAdriel\ValidatedDTO\Casting\BooleanCast;
use WendellAdriel\ValidatedDTO\Casting\IntegerCast;
use WendellAdriel\ValidatedDTO\Casting\StringCast;
use WendellAdriel\ValidatedDTO\ValidatedDTO;

/**
 */
class CreatePaymentInstallmentPeriodsDto extends ValidatedDTO
{
    public int $periods;
    public int $interval;
    public string $interval_type; // 'days', 'months', or 'years'

    public int $invoice_id;

    public ?bool $dry_run;

    /**
     * Validation rules for creating a product
     */
    public function rules(): array
    {
        return [
            'periods' => 'required|integer|min:1',
            'interval' => 'required|integer|min:1',
            'interval_type' => 'required|in:days,months,years',

            'invoice_id' => 'required|integer|exists:fin_invoices,id',

            'dry_run' => 'nullable|boolean', // Optional, defaults to false
        ];
    }

    public function casts(): array
    {
        return [
            'periods' => new IntegerCast(),
            'interval_type' => new StringCast(),
            'interval' => new IntegerCast(),
            'invoice_id' => new IntegerCast(),
            'dry_run' => new BooleanCast(),
        ];
    }

    public function defaults(): array
    {
        return [
            'dry_run' => false,
        ];
    }
}

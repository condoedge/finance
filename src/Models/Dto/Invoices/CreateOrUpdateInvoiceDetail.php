<?php

namespace Condoedge\Finance\Models\Dto\Invoices;

use Condoedge\Finance\Casts\SafeDecimal;
use Condoedge\Finance\Casts\SafeDecimalCast;
use WendellAdriel\ValidatedDTO\Casting\ArrayCast;
use WendellAdriel\ValidatedDTO\Casting\FloatCast;
use WendellAdriel\ValidatedDTO\Casting\IntegerCast;
use WendellAdriel\ValidatedDTO\Casting\StringCast;
use WendellAdriel\ValidatedDTO\Concerns\EmptyDefaults;
use WendellAdriel\ValidatedDTO\ValidatedDTO;

class CreateOrUpdateInvoiceDetail extends ValidatedDTO
{
    use EmptyDefaults;

    public ?int $id;
    public int $invoice_id;
    public string $name;
    public string $description;
    public int $quantity;
    public SafeDecimal $unit_price;
    public int $revenue_account_id;

    /**
     * The IDs of the taxes to be applied to this invoice detail.
     * @var int[]
     */
    public ?array $taxesIds;

    public function rules(): array
    {
        return [
            /**
             * Send this field as null to create a new invoice detail instead of updating it.
             * @var integer|null
             * @example null
             */
            'id' => 'nullable|integer|exists:fin_invoice_details,id',
            'invoice_id' => 'required|integer|exists:fin_invoices,id',
            'description' => 'sometimes|string|max:255',
            'name' => 'required|string|max:255',
            'quantity' => 'required|integer|min:1',
            'unit_price' => 'required|numeric|min:0',
            'revenue_account_id' => 'required|integer|exists:fin_accounts,id',

            'taxesIds' => 'nullable|array',
            'taxesIds.*' => 'integer|exists:fin_taxes,id',
        ];
    }

    public function casts(): array
    {
        return [
            'id' => new IntegerCast,
            'name' => new StringCast,
            'invoice_id' => new IntegerCast,
            'description' => new StringCast,
            'quantity' => new IntegerCast,
            'unit_price' => new SafeDecimalCast,
            'revenue_account_id' => new IntegerCast,
            'taxesIds' => new ArrayCast,
        ];
    }
}
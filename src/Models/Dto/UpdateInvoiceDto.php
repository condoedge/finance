<?php

namespace Condoedge\Finance\Models\Dto;

use Carbon\Carbon;
use Condoedge\Finance\Facades\PaymentTypeEnum;
use WendellAdriel\ValidatedDTO\Casting\ArrayCast;
use WendellAdriel\ValidatedDTO\Casting\CarbonCast;
use WendellAdriel\ValidatedDTO\Casting\IntegerCast;
use WendellAdriel\ValidatedDTO\Concerns\EmptyDefaults;
use WendellAdriel\ValidatedDTO\ValidatedDTO;

class UpdateInvoiceDto extends ValidatedDTO
{
    use EmptyDefaults;

    public int $id;
    public int $payment_type_id;
    public Carbon $invoice_date;
    public Carbon $invoice_due_date;

    public array $invoiceDetails;


    public function rules(): array
    {
        return [
            'id' => 'required|integer|exists:fin_invoices,id',
            'payment_type_id' => 'required|integer|in:' . collect(PaymentTypeEnum::getEnumClass()::cases())->pluck('value')->implode(','),
            'invoice_date' => 'required|date',
            'invoice_due_date' => 'required|date|after_or_equal:invoice_date',

            'customer_id' => 'prohibited',
            'invoice_type_id' => 'prohibited',

            'invoiceDetails' => 'array',
            /**
             * Send this field as null to create a new invoice details instead of updating it.
             * @var integer|null
             * @example null
             */
            'invoiceDetails.*.id' => 'nullable|integer|exists:fin_invoice_details,id',
            'invoiceDetails.*.description' => 'required|string|max:255',
            'invoiceDetails.*.quantity' => 'required|integer|min:1',
            'invoiceDetails.*.unit_price' => 'required|numeric|min:0',
            'invoiceDetails.*.revenue_account_id' => 'required|integer|exists:fin_accounts,id',
        ];
    }

    public function casts(): array
    {
        return [
            'id' => new IntegerCast,
            'payment_type_id' => new IntegerCast,
            'invoice_date' => new CarbonCast,
            'invoice_due_date' => new CarbonCast,
            'invoiceDetails' => new ArrayCast,
        ];
    }
}
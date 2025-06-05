<?php

namespace Condoedge\Finance\Models\Dto\Invoices;

use Carbon\Carbon;
use Condoedge\Finance\Facades\PaymentTypeEnum;
use WendellAdriel\ValidatedDTO\Casting\ArrayCast;
use WendellAdriel\ValidatedDTO\Casting\BooleanCast;
use WendellAdriel\ValidatedDTO\Casting\CarbonCast;
use WendellAdriel\ValidatedDTO\Casting\IntegerCast;
use WendellAdriel\ValidatedDTO\ValidatedDTO;

class CreateInvoiceDto extends ValidatedDTO
{
    public int $customer_id;
    public int $invoice_type_id;
    public int $payment_type_id;
    public Carbon $invoice_date;
    public ?Carbon $invoice_due_date;

    public bool $is_draft;

    public array $invoiceDetails;

    public function rules(): array
    {
        return [
            'customer_id' => 'required|integer|exists:fin_customers,id',
            'invoice_type_id' => 'required|integer|exists:fin_invoice_types,id',
            'payment_type_id' => 'required|integer|in:' . collect(PaymentTypeEnum::getEnumClass()::cases())->pluck('value')->implode(','),
            'invoice_date' => 'required|date',
            'invoice_due_date' => 'nullable|date|after_or_equal:invoice_date',
            'is_draft' => 'boolean',

            'invoiceDetails' => 'array',
            /**
             * Send this field as null to create a new invoice detail instead of updating it.
             * @var integer|null
             * @example null
             */
            'invoiceDetails.*.id' => 'nullable|integer|exists:fin_invoice_details,id',
            'invoiceDetails.*.name' => 'required|string|max:255',
            'invoiceDetails.*.description' => 'nullable|string|max:255',
            'invoiceDetails.*.quantity' => 'required|integer|min:1|max:2147483647',
            'invoiceDetails.*.unit_price' => 'required|numeric|gt:0|max:99999999999999.99999',
            'invoiceDetails.*.revenue_account_id' => 'required|integer|exists:fin_accounts,id',
            'invoiceDetails.*.taxesIds' => 'nullable|array',
            'invoiceDetails.*.taxesIds.*' => 'integer|exists:fin_taxes,id',
        ];
    }

    public function casts(): array
    {
        return [
            'customer_id' => new IntegerCast,
            'invoice_type_id' => new IntegerCast,
            'payment_type_id' => new IntegerCast,
            'invoice_date' => new CarbonCast,
            'invoice_due_date' => new CarbonCast,
            'invoiceDetails' => new ArrayCast,
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
<?php

namespace Condoedge\Finance\Models\Dto\Invoices;

use Carbon\Carbon;
use Condoedge\Finance\Facades\PaymentMethodEnum;
use WendellAdriel\ValidatedDTO\Casting\ArrayCast;
use WendellAdriel\ValidatedDTO\Casting\CarbonCast;
use WendellAdriel\ValidatedDTO\Casting\IntegerCast;
use WendellAdriel\ValidatedDTO\ValidatedDTO;

class UpdateInvoiceDto extends ValidatedDTO
{
    public int $id;
    public int $payment_method_id;
    public Carbon $invoice_date;
    public Carbon $invoice_due_date;

    public array $invoiceDetails;


    public function rules(): array
    {
        return [
            'id' => 'required|integer|exists:fin_invoices,id',
            'payment_method_id' => 'required|integer|in:' . collect(PaymentMethodEnum::getEnumClass()::cases())->pluck('value')->implode(','),
            'invoice_date' => 'required|date',
            'invoice_due_date' => 'required|date|after_or_equal:invoice_date',

            'customer_id' => 'prohibited',
            'invoice_type_id' => 'prohibited',

            'invoiceDetails' => 'array',
            /**
             * Send this field as null to create a new invoice details instead of updating it.
             *
             * @var int|null
             *
             * @example null
             */
            'invoiceDetails.*.id' => 'nullable|integer|exists:fin_invoice_details,id',
            'invoiceDetails.*.description' => 'required|string|max:255',
            'invoiceDetails.*.quantity' => 'required|integer|min:1',
            'invoiceDetails.*.unit_price' => 'required|numeric|min:0',
            'invoiceDetails.*.revenue_account_id' => 'required_without:invoiceDetails.*.revenue_natural_account_id|integer|exists:fin_gl_accounts,id',
            'invoiceDetails.*.revenue_natural_account_id' => 'required_without:invoiceDetails.*.revenue_account_id|integer|exists:fin_segment_values,id',

            'invoiceDetails.*.taxesIds' => 'nullable|array',
            'invoiceDetails.*.taxesIds.*' => 'integer|exists:fin_taxes,id',
            'invoiceDetails.*.product_id' => 'nullable|integer|exists:fin_products,id',
            'invoiceDetails.*.create_product_on_save' => 'nullable|boolean',
        ];
    }

    public function casts(): array
    {
        return [
            'id' => new IntegerCast(),
            'payment_method_id' => new IntegerCast(),
            'invoice_date' => new CarbonCast(),
            'invoice_due_date' => new CarbonCast(),
            'invoiceDetails' => new ArrayCast(),
        ];
    }

    public function defaults(): array
    {
        return [
            'invoiceDetails' => [],
        ];
    }
}

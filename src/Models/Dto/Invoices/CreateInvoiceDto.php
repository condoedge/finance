<?php

namespace Condoedge\Finance\Models\Dto\Invoices;

use Carbon\Carbon;
use Condoedge\Finance\Facades\PaymentMethodEnum;
use WendellAdriel\ValidatedDTO\Casting\ArrayCast;
use WendellAdriel\ValidatedDTO\Casting\BooleanCast;
use WendellAdriel\ValidatedDTO\Casting\CarbonCast;
use WendellAdriel\ValidatedDTO\Casting\IntegerCast;
use WendellAdriel\ValidatedDTO\ValidatedDTO;

/**
 * Create Invoice DTO
 * 
 * Used to create new invoices with associated details and tax information.
 * Supports both draft and final invoices creation.
 * 
 * @property int $customer_id The customer this invoice belongs to
 * @property int $invoice_type_id Type of invoice (from InvoiceTypeEnum)
 * @property int $payment_method_id Payment method type (from PaymentTypeEnum) 
 * @property Carbon $invoice_date Date the invoice was issued
 * @property Carbon|null $invoice_due_date Payment due date (optional)
 * @property bool $is_draft Whether this invoice is a draft
 * @property array $invoiceDetails Array of invoice line items
 */
class CreateInvoiceDto extends ValidatedDTO
{
    public int $customer_id;
    public int $invoice_type_id;
    public int $payment_method_id;

    public ?array $possible_payment_methods;
    public ?array $possible_payment_installments;

    public Carbon $invoice_date;
    public ?Carbon $invoice_due_date;

    public bool $is_draft;

    public array $invoiceDetails;

    public ?string $invoiceable_type = null;
    public ?int $invoiceable_id = null;

    public function rules(): array
    {
        return [
            'customer_id' => 'required|integer|exists:fin_customers,id',
            'invoice_type_id' => 'nullable|integer|exists:fin_invoice_types,id',
            'payment_method_id' => 'nullable|integer|in:' . collect(PaymentMethodEnum::getEnumClass()::cases())->pluck('value')->implode(','),
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
            'invoiceDetails.*.revenue_account_id' => 'required|integer|exists:fin_gl_accounts,id',
            'invoiceDetails.*.taxesIds' => 'nullable|array',
            'invoiceDetails.*.taxesIds.*' => 'integer|exists:fin_taxes,id',

            'possible_payment_methods' => 'nullable|array',
            'possible_payment_installments' => 'nullable|array',

            'invoiceable_type' => 'nullable|string',
            'invoiceable_id' => 'nullable|integer',
        ];
    }

    public function casts(): array
    {
        return [
            'customer_id' => new IntegerCast,
            'invoice_type_id' => new IntegerCast,
            'payment_method_id' => new IntegerCast,
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
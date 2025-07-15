<?php

namespace Condoedge\Finance\Models\Dto\Invoices;

use Carbon\Carbon;
use Condoedge\Finance\Facades\PaymentMethodEnum;
use Illuminate\Contracts\Validation\Validator;
use WendellAdriel\ValidatedDTO\Casting\ArrayCast;
use WendellAdriel\ValidatedDTO\Casting\CarbonCast;
use WendellAdriel\ValidatedDTO\Casting\IntegerCast;
use WendellAdriel\ValidatedDTO\ValidatedDTO;

class UpdateInvoiceDto extends ValidatedDTO
{
    public int $id;
    public ?int $payment_method_id;
    public ?int $payment_term_id;
    public ?array $possible_payment_methods;
    public ?array $possible_payment_terms;
    public ?Carbon $invoice_date;
    // public Carbon $invoice_due_date;

    public ?array $invoiceDetails;


    public function rules(): array
    {
        return [
            'id' => 'required|integer|exists:fin_invoices,id',
            'payment_method_id' => 'nullable|integer|in:' . collect(PaymentMethodEnum::getEnumClass()::cases())->pluck('value')->implode(','),
            'payment_term_id' => 'nullable|integer|exists:fin_payment_terms,id',
            'invoice_date' => 'nullable|date',
            // 'invoice_due_date' => 'required|date|after_or_equal:invoice_date',

            'possible_payment_methods' => 'required_without:payment_method_id|array',
            'possible_payment_terms' => 'required_without:payment_term_id|array',

            'customer_id' => 'prohibited',
            'invoice_type_id' => 'prohibited',

            'invoiceDetails' => 'nullable|array',
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
            'payment_term_id' => new IntegerCast(),
            'invoice_date' => new CarbonCast(),
            // 'invoice_due_date' => new CarbonCast(),
            'invoiceDetails' => new ArrayCast(),
        ];
    }

    public function defaults(): array
    {
        return [
            // 'invoiceDetails' => [],
            // 'possible_payment_methods' => [],
            // 'possible_payment_terms' => [],
        ];
    }

    public function after(Validator $validator): void
    {
        if ($validator->errors()->has('payment_term_id') || $validator->errors()->has('possible_payment_terms')) {
            $validator->errors()->add('payment_term_type', __('validation-payment-term-required'));
        }
    }
}

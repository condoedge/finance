<?php

namespace Condoedge\Finance\Models\Dto\Payments;

use Illuminate\Support\Facades\DB;
use WendellAdriel\ValidatedDTO\Casting\IntegerCast;

class CreateCustomerPaymentForInvoiceDto extends CreateCustomerPaymentDto
{
    public int $invoice_id;

    // Get the customer_id from the invoice dynamically
    public int $customer_id;

    /**
     * Set only by the refund path. A credit note is applied to invoices or refunded,
     * never paid, so every other caller is rejected.
     */
    public ?bool $is_refund;

    public function rules(): array
    {
        $previousRules = parent::rules();
        unset($previousRules['customer_id']);

        return [
            ...$previousRules,
            'invoice_id' => ['required', 'integer', 'exists:fin_invoices,id'],
            'is_refund' => ['nullable', 'boolean'],
        ];
    }

    public function casts(): array
    {
        $previousCasts = parent::casts();
        unset($previousCasts['customer_id']);

        return [
            ...parent::casts(),
            'invoice_id' => new IntegerCast(),
        ];
    }

    protected function after(\Illuminate\Validation\Validator $validator): void
    {
        parent::after($validator);

        $invoiceId = $this->dtoData['invoice_id'] ?? null;

        if (!$invoiceId) {
            return;
        }

        $this->customer_id = DB::table('fin_invoices')
            ->where('id', $invoiceId)
            ->value('customer_id');

        if (!($this->dtoData['is_refund'] ?? false) && $this->targetIsCreditNote($invoiceId)) {
            $validator->errors()->add('invoice_id', __('finance-payment-credit-note-not-allowed'));
        }
    }

    protected function targetIsCreditNote(int $invoiceId): bool
    {
        return DB::table('fin_invoices')
            ->join('fin_invoice_types', 'fin_invoice_types.id', '=', 'fin_invoices.invoice_type_id')
            ->where('fin_invoices.id', $invoiceId)
            ->value('fin_invoice_types.sign_multiplier') < 0;
    }
}

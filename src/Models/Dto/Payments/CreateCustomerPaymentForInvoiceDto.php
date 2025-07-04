<?php

namespace Condoedge\Finance\Models\Dto\Payments;

use Illuminate\Support\Facades\DB;
use WendellAdriel\ValidatedDTO\Casting\IntegerCast;

class CreateCustomerPaymentForInvoiceDto extends CreateCustomerPaymentDto
{
    public int $invoice_id;

    // Get the customer_id from the invoice dynamically
    public int $customer_id;

    public function rules(): array
    {
        $previousRules = parent::rules();
        unset($previousRules['customer_id']);

        return [
            ...$previousRules,
            'invoice_id' => ['required', 'integer', 'exists:fin_invoices,id'],
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

        if ($invoiceId) {
            $this->customer_id = DB::table('fin_invoices')
                ->where('id', $invoiceId)
                ->value('customer_id');
        }
    }
}

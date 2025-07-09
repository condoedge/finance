<?php

namespace Condoedge\Finance\Models\Dto\Invoices;

use Condoedge\Finance\Facades\InvoiceModel;
use Illuminate\Contracts\Validation\Validator;
use WendellAdriel\ValidatedDTO\Casting\IntegerCast;
use WendellAdriel\ValidatedDTO\Concerns\EmptyDefaults;
use WendellAdriel\ValidatedDTO\ValidatedDTO;

class ApproveInvoiceDto extends ValidatedDTO
{
    use EmptyDefaults;

    public int $invoice_id;

    public ?int $payment_method_id;
    public ?int $payment_term_id;

    public function rules(): array
    {
        return [
            'invoice_id' => 'required|integer|exists:fin_invoices,id',
            'payment_method_id' => 'nullable|integer|exists:fin_payment_methods,id',
            'payment_term_id' => 'nullable|integer|exists:fin_payment_terms,id',
        ];
    }

    public function casts(): array
    {
        return [
            'invoice_id' => new IntegerCast(),
            'payment_method_id' => new IntegerCast(),
            'payment_term_id' => new IntegerCast(),
        ];
    }

    public function defaults(): array
    {
        return [
            'payment_method_id' => null,
            'payment_term_id' => null,
        ];
    }

    public function after(Validator $validator): void
    {
        $invoiceId = $this->dtoData['invoice_id'] ?? null;
        $paymentTermId = $this->dtoData['payment_term_id'] ?? null;
        $paymentMethodId = $this->dtoData['payment_method_id'] ?? null;
        
        if ($invoiceId) {
            $invoice = InvoiceModel::find($invoiceId);

            if (!$invoice->payment_method_id && !$paymentMethodId) {
                $validator->errors()->add('payment_method_id', __('translate.payment-method-required'));
            }

            if (!$invoice->payment_term_id && !$paymentTermId) {
                $validator->errors()->add('payment_term_id', __('translate.payment-term-required'));
            }
        }
    }
}

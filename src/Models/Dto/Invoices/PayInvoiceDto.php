<?php

namespace Condoedge\Finance\Models\Dto\Invoices;

use Condoedge\Finance\Facades\InvoiceModel;
use Illuminate\Contracts\Validation\Validator;
use WendellAdriel\ValidatedDTO\Casting\ArrayCast;
use WendellAdriel\ValidatedDTO\Casting\BooleanCast;
use WendellAdriel\ValidatedDTO\Casting\IntegerCast;
use WendellAdriel\ValidatedDTO\Concerns\EmptyDefaults;
use WendellAdriel\ValidatedDTO\ValidatedDTO;

class PayInvoiceDto extends ValidatedDTO
{
    use EmptyDefaults;

    public int $invoice_id;

    public ?int $payment_method_id;
    public ?int $payment_term_id;

    public ?array $installment_ids;

    public ?bool $pay_next_installment;

    public function rules(): array
    {
        return [
            'invoice_id' => 'required|integer|exists:fin_invoices,id',
            'payment_method_id' => 'nullable|integer|exists:fin_payment_methods,id',
            'payment_term_id' => 'nullable|integer|exists:fin_payment_terms,id',

            'installment_ids' => 'nullable|array',
            'installment_ids.*' => 'integer|exists:fin_payment_installment_periods,id',
        ];
    }

    public function casts(): array
    {
        return [
            'invoice_id' => new IntegerCast(),
            'payment_method_id' => new IntegerCast(),
            'payment_term_id' => new IntegerCast(),
            'installment_ids' => new ArrayCast(),
            'pay_next_installment' => new BooleanCast(),
        ];
    }

    public function defaults(): array
    {
        return [
            'installment_ids' => null,
        ];
    }

    public function after(Validator $validator): void
    {
        $invoiceId = $this->dtoData['invoice_id'] ?? null;
        $payNextInstallment = $this->dtoData['pay_next_installment'] ?? false;

        if ($invoiceId && $payNextInstallment) {
            $invoice = InvoiceModel::find($invoiceId);

            $this->dtoData['installment_ids'] = array_filter(array_merge(
                $this->dtoData['installment_ids'] ?? [],
                [$invoice->installmentsPeriods()->where('due_amount', '>', 0)->first()?->id]
            ));
        }
    }
}

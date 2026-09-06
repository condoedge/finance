<?php

namespace Condoedge\Finance\Models\Dto\Invoices;

use Condoedge\Finance\Facades\InvoiceModel;
use Illuminate\Validation\Validator;
use WendellAdriel\ValidatedDTO\Casting\IntegerCast;
use WendellAdriel\ValidatedDTO\Concerns\EmptyDefaults;
use WendellAdriel\ValidatedDTO\ValidatedDTO;

/**
 * Void an invoice. Irreversible: an approved invoice is reversed by a full credit note
 * dated today, and a draft is simply flagged.
 *
 * @property int $invoice_id The invoice to void
 */
class VoidInvoiceDto extends ValidatedDTO
{
    use EmptyDefaults;

    public int $invoice_id;

    public function rules(): array
    {
        return [
            'invoice_id' => 'required|integer|exists:fin_invoices,id',
        ];
    }

    public function casts(): array
    {
        return [
            'invoice_id' => new IntegerCast(),
        ];
    }

    public function after(Validator $validator): void
    {
        $invoice = InvoiceModel::find($this->dtoData['invoice_id'] ?? null);

        if (!$invoice) {
            return;
        }

        // The same predicate the button reads, so the modal cannot offer what the
        // service refuses. Re-checked in the service, under the transaction.
        if ($reason = $invoice->voidRefusalReason()) {
            $validator->errors()->add('invoice_id', __($reason));
        }
    }
}

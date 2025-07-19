<?php

namespace Condoedge\Finance\Models\Dto\Invoices;

use Condoedge\Finance\Facades\InvoiceModel;
use Condoedge\Finance\Models\Dto\Customers\CreateAddressDto;
use Illuminate\Contracts\Validation\Validator;
use WendellAdriel\ValidatedDTO\Casting\DTOCast;
use WendellAdriel\ValidatedDTO\Casting\IntegerCast;
use WendellAdriel\ValidatedDTO\Concerns\EmptyDefaults;
use WendellAdriel\ValidatedDTO\ValidatedDTO;

class ApproveInvoiceDto extends ValidatedDTO
{
    use EmptyDefaults;

    public int $invoice_id;

    public ?CreateAddressDto $address;

    public function rules(): array
    {
        return [
            'invoice_id' => 'required|integer|exists:fin_invoices,id',
            'address' => 'nullable|array',
        ];
    }

    public function casts(): array
    {
        return [
            'invoice_id' => new IntegerCast(),
            'address' => new DTOCast(CreateAddressDto::class),
        ];
    }

    public function defaults(): array
    {
        return [
        ];
    }

    public function after(Validator $validator): void
    {
        $invoiceId = $this->dtoData['invoice_id'] ?? null;
        $addressData = $this->dtoData['address'] ?? null;

        if ($invoiceId) {
            $invoice = InvoiceModel::find($invoiceId);

            if (!$invoice->address && !$addressData) {
                $validator->errors()->add('address', __('finance-address-required'));
            }
        }
    }
}

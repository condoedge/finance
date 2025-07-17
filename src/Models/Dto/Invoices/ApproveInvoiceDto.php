<?php

namespace Condoedge\Finance\Models\Dto\Invoices;

use Condoedge\Finance\Facades\InvoiceModel;
use Condoedge\Finance\Models\Dto\Customers\CreateAddressDto;
use Condoedge\Utils\Models\ContactInfo\Maps\Address;
use Illuminate\Contracts\Validation\Validator;
use WendellAdriel\ValidatedDTO\Casting\DTOCast;
use WendellAdriel\ValidatedDTO\Casting\IntegerCast;
use WendellAdriel\ValidatedDTO\Concerns\EmptyDefaults;
use WendellAdriel\ValidatedDTO\ValidatedDTO;

class ApproveInvoiceDto extends ValidatedDTO
{
    use EmptyDefaults;

    public int $invoice_id;

    public ?int $payment_method_id;
    public ?int $payment_term_id;
    public ?CreateAddressDto $address;

    public function rules(): array
    {
        return [
            'invoice_id' => 'required|integer|exists:fin_invoices,id',
            'payment_method_id' => 'nullable|integer|exists:fin_payment_methods,id',
            'payment_term_id' => 'nullable|integer|exists:fin_payment_terms,id',
            'address' => 'nullable|array',
        ];
    }

    public function casts(): array
    {
        return [
            'invoice_id' => new IntegerCast(),
            'payment_method_id' => new IntegerCast(),
            'payment_term_id' => new IntegerCast(),
            'address' => new DTOCast(CreateAddressDto::class),
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
        $addressData = $this->dtoData['address'] ?? null;

        if ($invoiceId) {
            $invoice = InvoiceModel::find($invoiceId);

            if (!$invoice->payment_method_id && !$paymentMethodId) {
                $validator->errors()->add('payment_method_id', __('finance-payment-method-required'));
            }

            if (!$invoice->payment_term_id && !$paymentTermId) {
                $validator->errors()->add('payment_term_id', __('finance-payment-term-required'));
            }

            if (!$invoice->address && !$addressData) {
                $validator->errors()->add('address', __('finance-address-required'));
            }
        }
    }
}

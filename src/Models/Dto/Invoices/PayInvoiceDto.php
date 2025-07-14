<?php

namespace Condoedge\Finance\Models\Dto\Invoices;

use Condoedge\Finance\Models\Dto\Customers\CreateAddressDto;
use WendellAdriel\ValidatedDTO\Casting\ArrayCast;
use WendellAdriel\ValidatedDTO\Casting\BooleanCast;
use WendellAdriel\ValidatedDTO\Casting\DTOCast;
use WendellAdriel\ValidatedDTO\Casting\IntegerCast;
use WendellAdriel\ValidatedDTO\Concerns\EmptyDefaults;
use WendellAdriel\ValidatedDTO\ValidatedDTO;

class PayInvoiceDto extends ValidatedDTO
{
    use EmptyDefaults;

    public int $invoice_id;

    public ?int $payment_method_id;
    public ?int $payment_term_id;

    public ?int $installment_id;

    public ?bool $pay_next_installment;

    public ?CreateAddressDto $address;

    public ?array $request_data;

    public function rules(): array
    {
        return [
            'invoice_id' => 'required|integer|exists:fin_invoices,id',
            'payment_method_id' => 'nullable|integer|exists:fin_payment_methods,id',
            'payment_term_id' => 'nullable|integer|exists:fin_payment_terms,id',

            'installment_id' => 'nullable|integer|exists:fin_payment_installment_periods,id',

            'pay_next_installment' => 'nullable|boolean',

            'address' => 'nullable|array',

            'request_data' => 'nullable|array',
        ];
    }

    public function casts(): array
    {
        return [
            'invoice_id' => new IntegerCast(),
            'payment_method_id' => new IntegerCast(),
            'payment_term_id' => new IntegerCast(),
            'installment_id' => new IntegerCast(),
            'pay_next_installment' => new BooleanCast(),

            'address' => new DTOCast(CreateAddressDto::class),

            'request_data' => new ArrayCast(),
        ];
    }

    public function defaults(): array
    {
        return [
            'installment_id' => null,
            'request_data' => [],
        ];
    }
}

<?php

namespace Condoedge\Finance\Models\Dto\Invoices;

use Condoedge\Finance\Models\Dto\Customers\CreateAddressDto;
use WendellAdriel\ValidatedDTO\Casting\DTOCast;
use WendellAdriel\ValidatedDTO\Casting\IntegerCast;
use WendellAdriel\ValidatedDTO\Concerns\EmptyDefaults;
use WendellAdriel\ValidatedDTO\ValidatedDTO;

class ApproveInvoiceDto extends ValidatedDTO
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
            'address' => new DTOCast(CreateAddressDto::class),
        ];
    }

    public function defaults(): array
    {
        return [
        ];
    }
}

<?php

namespace Condoedge\Finance\Models\Dto\Invoices;

use WendellAdriel\ValidatedDTO\Casting\ArrayCast;
use WendellAdriel\ValidatedDTO\Concerns\EmptyDefaults;
use WendellAdriel\ValidatedDTO\ValidatedDTO;

class ApproveManyInvoicesDto extends ValidatedDTO
{
    use EmptyDefaults;

    public array $invoices_ids;

    public function rules(): array
    {
        return [
            'invoices_ids' => 'required|array',
            'invoices_ids.*' => 'required|integer|exists:fin_invoices,id',
        ];
    }

    public function casts(): array
    {
        return [
            'invoice_id' => new ArrayCast(),
        ];
    }
}

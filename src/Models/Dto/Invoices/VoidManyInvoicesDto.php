<?php

namespace Condoedge\Finance\Models\Dto\Invoices;

use WendellAdriel\ValidatedDTO\Casting\ArrayCast;
use WendellAdriel\ValidatedDTO\Concerns\EmptyDefaults;
use WendellAdriel\ValidatedDTO\ValidatedDTO;

/**
 * Void a selection of invoices. Voidability is not asserted here: a batch skips what it
 * cannot void rather than refusing the whole selection over one paid invoice.
 */
class VoidManyInvoicesDto extends ValidatedDTO
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
            'invoices_ids' => new ArrayCast(),
        ];
    }
}

<?php

namespace Condoedge\Finance\Kompo\Taxes;

use Condoedge\Finance\Facades\TaxModel;
use Condoedge\Utils\Kompo\Common\WhiteTable;

class TaxesTable extends WhiteTable
{
    public $id = 'taxes-table';

    public function query()
    {
        return TaxModel::query();
    }

    public function headers()
    {
        return [
            _Th('finance-name'),
            _Th('finance-rate'),
            _Th('finance-valid-from'),
        ];
    }

    public function render($tax)
    {
        return _TableRow(
            _Html($tax->name),
            _Html($tax->rate->multiply(100)->round(2) . '%'),
            _Html($tax->valide_from?->format('Y-m-d') ?: '-'),
        )->selfGet('getTaxForm', ['tax_id' => $tax->id])->inModal();
    }

    public function getTaxForm($taxId)
    {
        return new TaxForm($taxId);
    }
}

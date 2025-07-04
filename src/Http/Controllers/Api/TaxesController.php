<?php

namespace Condoedge\Finance\Http\Controllers\Api;

use Condoedge\Finance\Facades\InvoiceDetailService;
use Condoedge\Finance\Models\Dto\Taxes\UpsertManyTaxDetailDto;
use Condoedge\Finance\Models\Dto\Taxes\UpsertTaxDetailDto;
use Illuminate\Routing\Controller;

class TaxesController extends Controller
{
    /**
     * @operationId Sync many taxes to invoice detail
     */
    public function syncTaxes(UpsertManyTaxDetailDto $data)
    {
        InvoiceDetailService::applyTaxesToDetail($data);

        return response()->json(['message' => 'synched taxes']);
    }

    /**
     * @operationId Add a tax to invoice detail
     */
    public function addTax(UpsertTaxDetailDto $data)
    {
        InvoiceDetailService::applyTaxesToDetail($data);

        return response()->json(['message' => 'tax added']);
    }
}

<?php

namespace Condoedge\Finance\Http;

use Condoedge\Finance\Models\Dto\UpsertManyTaxDetailDto;
use Condoedge\Finance\Models\Dto\UpsertTaxDetailDto;
use Condoedge\Finance\Models\InvoiceDetailTax;
use Illuminate\Routing\Controller;

class TaxesController extends Controller
{
    /**
     * @operationId Sync many taxes to invoice detail
     */
    public function syncTaxes(UpsertManyTaxDetailDto $data)
    {
        InvoiceDetailTax::upsertManyForInvoiceDetail($data);

        return response()->json(['message' => 'synched taxes']);
    }

    /**
     * @operationId Add a tax to invoice detail
     */
    public function addTax(UpsertTaxDetailDto $data)
    {
        InvoiceDetailTax::upsertForInvoiceDetailFromTax($data);

        return response()->json(['message' => 'tax added']);
    }
}
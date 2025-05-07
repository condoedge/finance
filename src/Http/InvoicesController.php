<?php

namespace Condoedge\Finance\Http;

use Condoedge\Finance\Facades\InvoiceDetailModel;
use Condoedge\Finance\Facades\InvoiceModel;
use Condoedge\Finance\Models\Dto\Invoices\CreateInvoiceDto;
use Condoedge\Finance\Models\Dto\Invoices\CreateOrUpdateInvoiceDetail;
use Condoedge\Finance\Models\Dto\Invoices\UpdateInvoiceDto;
use Illuminate\Routing\Controller;

class InvoicesController extends Controller
{
    /**
     * @operationId Create invoice
     */
    public function createInvoice(CreateInvoiceDto $data)
    {
        InvoiceModel::createInvoiceFromDto($data);

        return response()->json([
            'message' => __('translate.invoice-created'),
        ]);
    }

    /**
     * @operationId Update invoice
     */
    public function updateInvoice(UpdateInvoiceDto $data)
    {
        InvoiceModel::updateInvoiceFromDto($data);

        return response()->json([
            'message' => __('translate.invoice-updated'),
        ]);
    }

    /**
     * @operationId Create or update invoice detail
     */
    public function saveInvoiceDetail(CreateOrUpdateInvoiceDetail $data)
    {
        if ($data->id) {
            InvoiceDetailModel::editInvoiceDetail($data);
        } else {
            InvoiceDetailModel::createInvoiceDetail($data);
        }

        return response()->json([
            'message' => __('translate.invoice-detail-created'),
        ]);
    }
}
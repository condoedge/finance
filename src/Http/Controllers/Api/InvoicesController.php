<?php

namespace Condoedge\Finance\Http\Controllers\Api;

use Condoedge\Finance\Facades\InvoiceDetailService;
use Condoedge\Finance\Facades\InvoiceService;
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
        InvoiceService::createInvoice($data);

        return response()->json([
            'message' => __('finance.invoice-created'),
        ]);
    }

    /**
     * @operationId Update invoice
     */
    public function updateInvoice(UpdateInvoiceDto $data)
    {
        InvoiceService::updateInvoice($data);

        return response()->json([
            'message' => __('finance-invoice-updated'),
        ]);
    }

    /**
     * @operationId Create or update invoice detail
     */
    public function saveInvoiceDetail(CreateOrUpdateInvoiceDetail $data)
    {
        if ($data->id) {
            InvoiceDetailService::updateInvoiceDetail($data);
        } else {
            InvoiceDetailService::createInvoiceDetail($data);
        }

        return response()->json([
            'message' => __('finance-invoice-detail-created'),
        ]);
    }
}

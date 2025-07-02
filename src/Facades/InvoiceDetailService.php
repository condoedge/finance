<?php

namespace Condoedge\Finance\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Invoice Detail Service Facade
 * 
 * @method static \Condoedge\Finance\Models\InvoiceDetail createInvoiceDetail(\Condoedge\Finance\Models\Dto\Invoices\CreateOrUpdateInvoiceDetail $dto)
 * @method static \Condoedge\Finance\Models\InvoiceDetail updateInvoiceDetail(\Condoedge\Finance\Models\Dto\Invoices\CreateOrUpdateInvoiceDetail $dto)
 * @method static bool deleteInvoiceDetail(\Condoedge\Finance\Models\InvoiceDetail $invoiceDetail)
 * @method static \Illuminate\Support\Collection applyTaxesToDetail(\Condoedge\Finance\Models\Dto\Taxes\UpsertManyTaxDetailDto $dto)
 * @method static bool removeTaxesFromDetail(\Condoedge\Finance\Models\InvoiceDetail $invoiceDetail, \Illuminate\Support\Collection $taxIds)
 * @method static \Condoedge\Finance\Casts\SafeDecimal calculateExtendedPrice(int $quantity, \Condoedge\Finance\Casts\SafeDecimal $unitPrice)
 * @method static \Condoedge\Finance\Casts\SafeDecimal calculateTotalTaxAmount(\Condoedge\Finance\Models\InvoiceDetail $invoiceDetail)
 * @method static \Condoedge\Finance\Casts\SafeDecimal calculateTotalAmount(\Condoedge\Finance\Models\InvoiceDetail $invoiceDetail)
 * @method static \Illuminate\Support\Collection getDetailTaxes(\Condoedge\Finance\Models\InvoiceDetail $invoiceDetail, string|null $taxName = null)
 * @method static \Illuminate\Support\Collection getInvoiceTaxes(\Condoedge\Finance\Models\Invoice $invoice, string|null $taxName = null)
 * @method static \Condoedge\Finance\Models\InvoiceDetail copyDetailToInvoice(\Condoedge\Finance\Models\InvoiceDetail $sourceDetail, \Condoedge\Finance\Models\Invoice $targetInvoice)
 * @method static \Illuminate\Support\Collection createBulkDetails(\Condoedge\Finance\Models\Invoice $invoice, \Illuminate\Support\Collection $detailsData)
 * 
 * @see \Condoedge\Finance\Services\InvoiceDetail\InvoiceDetailServiceInterface
 */
class InvoiceDetailService extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Condoedge\Finance\Services\InvoiceDetail\InvoiceDetailServiceInterface::class;
    }
}

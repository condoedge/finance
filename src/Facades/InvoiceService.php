<?php

namespace Condoedge\Finance\Facades;

use Condoedge\Finance\Services\Invoice\InvoiceServiceInterface;
use Illuminate\Support\Facades\Facade;

/**
 * Invoice Service Facade
 * 
 * @method static \Condoedge\Finance\Models\Invoice upsertInvoice(\Condoedge\Finance\Models\Dto\Invoices\CreateInvoiceDto|\Condoedge\Finance\Models\Dto\Invoices\UpdateInvoiceDto $dto)
 * @method static \Condoedge\Finance\Models\Invoice createInvoice(\Condoedge\Finance\Models\Dto\Invoices\CreateInvoiceDto $dto)
 * @method static \Condoedge\Finance\Models\Invoice updateInvoice(\Condoedge\Finance\Models\Dto\Invoices\UpdateInvoiceDto $dto)
 * @method static \Condoedge\Finance\Models\Invoice approveInvoice(\Condoedge\Finance\Models\Dto\Invoices\ApproveInvoiceDto $dto)
 * @method static \Illuminate\Support\Collection approveMany(\Condoedge\Finance\Models\Dto\Invoices\ApproveManyInvoicesDto $dto)
 * @method static \Illuminate\Support\Collection getDefaultTaxesIds(\Condoedge\Finance\Models\Invoice $invoice)
 * @method static array calculateInvoiceTotals(\Condoedge\Finance\Models\Invoice $invoice)
 * @method static bool validateInvoiceBusinessRules(\Condoedge\Finance\Models\Invoice $invoice)
 * 
 * @see \Condoedge\Finance\Services\Invoice\InvoiceServiceInterface
 */
class InvoiceService extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return InvoiceServiceInterface::class;
    }
}

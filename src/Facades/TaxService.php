<?php

namespace Condoedge\Finance\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Tax Service Facade
 * 
 * @method static \Illuminate\Support\Collection getActiveTaxes(int|null $teamId = null)
 * @method static \Condoedge\Finance\Models\TaxGroup getDefaultTaxGroupForCustomer(\Condoedge\Finance\Models\Customer $customer)
 * @method static \Illuminate\Support\Collection getTaxesForInvoice(\Condoedge\Finance\Models\Invoice $invoice)
 * @method static \Condoedge\Finance\Casts\SafeDecimal calculateTaxAmount(\Condoedge\Finance\Casts\SafeDecimal $baseAmount, \Condoedge\Finance\Models\Tax $tax)
 * @method static \Condoedge\Finance\Casts\SafeDecimal calculateTotalTaxForInvoiceDetail(\Condoedge\Finance\Models\InvoiceDetail $invoiceDetail)
 * @method static \Illuminate\Support\Collection getTaxBreakdownForInvoiceDetail(\Condoedge\Finance\Models\InvoiceDetail $invoiceDetail)
 * @method static bool validateTaxIsActive(\Condoedge\Finance\Models\Tax $tax, \DateTime|null $date = null)
 * @method static \Condoedge\Finance\Models\TaxGroup createTaxGroup(string $name, \Illuminate\Support\Collection $taxIds, int|null $teamId = null)
 * @method static \Condoedge\Finance\Models\TaxGroup updateTaxGroupTaxes(\Condoedge\Finance\Models\TaxGroup $taxGroup, \Illuminate\Support\Collection $taxIds)
 * @method static \Illuminate\Support\Collection getTaxGroupsForTeam(int|null $teamId = null)
 * @method static \Condoedge\Finance\Casts\SafeDecimal calculateCompoundTaxAmount(\Condoedge\Finance\Casts\SafeDecimal $baseAmount, \Illuminate\Support\Collection $taxes, bool $compoundMode = false)
 * 
 * @see \Condoedge\Finance\Services\Tax\TaxServiceInterface
 */
class TaxService extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Condoedge\Finance\Services\Tax\TaxServiceInterface::class;
    }
}

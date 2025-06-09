<?php

namespace Condoedge\Finance\Services\Tax;

use Condoedge\Finance\Models\Tax;
use Condoedge\Finance\Models\TaxGroup;
use Condoedge\Finance\Models\Invoice;
use Condoedge\Finance\Models\InvoiceDetail;
use Condoedge\Finance\Models\Customer;
use Condoedge\Finance\Casts\SafeDecimal;
use Illuminate\Support\Collection;

/**
 * Interface for Tax Service
 * 
 * This interface allows easy override of tax business logic
 * by implementing this interface in external packages or custom services.
 */
interface TaxServiceInterface
{
    /**
     * Get active taxes for a specific team
     * 
     * @param int|null $teamId
     * @return Collection<Tax>
     */
    public function getActiveTaxes(?int $teamId = null): Collection;
    
    /**
     * Get default tax group for customer
     * 
     * @param Customer $customer
     * @return TaxGroup
     * @throws \Exception When no tax group can be resolved
     */
    public function getDefaultTaxGroupForCustomer(Customer $customer): TaxGroup;
    
    /**
     * Get taxes that should be applied to an invoice
     * 
     * @param Invoice $invoice
     * @return Collection<Tax>
     */
    public function getTaxesForInvoice(Invoice $invoice): Collection;
    
    /**
     * Calculate total tax amount for invoice detail
     * 
     * @param InvoiceDetail $invoiceDetail
     * @return SafeDecimal
     */
    public function calculateTotalTaxForInvoiceDetail(InvoiceDetail $invoiceDetail): SafeDecimal;
    
    /**
     * This is reduntant, we're doing it into the database.
     * Calculate total tax amount based on base amount and tax 
     * 
     * @param InvoiceDetail $invoiceDetail
     * @return SafeDecimal
     */
    public function calculateTaxAmount(SafeDecimal $baseAmount, Tax $tax): SafeDecimal;

    /**
     * Get tax breakdown for invoice detail
     * 
     * @param InvoiceDetail $invoiceDetail
     * @return Collection Array with tax_id, tax_rate, tax_amount
     */
    public function getTaxBreakdownForInvoiceDetail(InvoiceDetail $invoiceDetail): Collection;
    
    /**
     * Create tax group with taxes
     * 
     * @param string $name
     * @param Collection<int> $taxIds
     * @param int|null $teamId
     * @return TaxGroup
     * @throws \Exception When tax IDs are invalid
     */
    public function createTaxGroup(string $name, Collection $taxIds, ?int $teamId = null): TaxGroup;
    
    /**
     * Update tax group taxes
     * 
     * @param TaxGroup $taxGroup
     * @param Collection<int> $taxIds
     * @return TaxGroup
     * @throws \Exception When tax IDs are invalid
     */
    public function updateTaxGroupTaxes(TaxGroup $taxGroup, Collection $taxIds): TaxGroup;
    
    /**
     * Get tax groups for team
     * 
     * @param int|null $teamId
     * @return Collection<TaxGroup>
     */
    public function getTaxGroupsForTeam(?int $teamId = null): Collection;
    
    /**
     * Calculate compound tax amount (tax on tax)
     * 
     * @param SafeDecimal $baseAmount
     * @param Collection<Tax> $taxes
     * @param bool $compoundMode
     * @return SafeDecimal
     */
    public function calculateCompoundTaxAmount(SafeDecimal $baseAmount, Collection $taxes, bool $compoundMode = false): SafeDecimal;
}

<?php

namespace Condoedge\Finance\Services\Tax;

use Condoedge\Finance\Models\Tax;
use Condoedge\Finance\Models\TaxGroup;
use Condoedge\Finance\Models\Invoice;
use Condoedge\Finance\Models\InvoiceDetail;
use Condoedge\Finance\Models\Customer;
use Condoedge\Finance\Casts\SafeDecimal;
use Condoedge\Utils\Facades\GlobalConfig;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Tax Service Implementation
 * 
 * Handles all tax business logic including tax calculations, tax group management,
 * validation, and invoice tax application.
 * 
 * This implementation can be easily overridden by binding a custom 
 * implementation to the TaxServiceInterface in your service provider.
 */
class TaxService implements TaxServiceInterface
{
    /**
     * Get active taxes for team
     */
    public function getActiveTaxes(?int $teamId = null): Collection
    {
        return Tax::active()
            ->forTeam($teamId)
            ->orderBy('name')
            ->get();
    }
    
    /**
     * Get default tax group for customer
     */
    public function getDefaultTaxGroupForCustomer(Customer $customer): TaxGroup
    {
        // Try customer's default address tax group first
        $taxGroupId = $customer->defaultAddress?->tax_group_id 
            ?? GlobalConfig::getOrFail('default_tax_group_id');
            
        $taxGroup = TaxGroup::find($taxGroupId);
        
        if (!$taxGroup) {
            throw new \Exception("Tax group {$taxGroupId} not found for customer {$customer->id}");
        }
        
        return $taxGroup;
    }
    
    /**
     * Get taxes for invoice
     */
    public function getTaxesForInvoice(Invoice $invoice): Collection
    {
        $taxGroup = $this->getDefaultTaxGroupForCustomer($invoice->customer);
        
        return $taxGroup->taxes()
            ->active()
            ->get();
    }
    
    /**
     * Calculate tax amount for base amount
     */
    public function calculateTaxAmount(SafeDecimal $baseAmount, Tax $tax): SafeDecimal
    {
        // Calculate: baseAmount * (tax_rate / 100)
        return $baseAmount->multiply($tax->rate);
    }
    
    /**
     * Calculate total tax for invoice detail
     */
    public function calculateTotalTaxForInvoiceDetail(InvoiceDetail $invoiceDetail): SafeDecimal
    {
        $taxes = $this->getTaxesForInvoice($invoiceDetail->invoice);
        $baseAmount = $invoiceDetail->extended_price;
        
        return $this->calculateSimpleTaxSum($baseAmount, $taxes);
    }
    
    /**
     * Get tax breakdown for invoice detail
     */
    public function getTaxBreakdownForInvoiceDetail(InvoiceDetail $invoiceDetail): Collection
    {
        $taxes = $this->getTaxesForInvoice($invoiceDetail->invoice);
        $baseAmount = $invoiceDetail->extended_price;
        
        return $taxes->map(function (Tax $tax) use ($baseAmount) {
            return [
                'tax_id' => $tax->id,
                'tax_name' => $tax->name,
                'tax_rate' => $tax->rate,
                'tax_amount' => $this->calculateTaxAmount($baseAmount, $tax),
                'base_amount' => $baseAmount,
            ];
        });
    }

    
    /**
     * Create tax group with taxes
     */
    public function createTaxGroup(string $name, Collection $taxIds, ?int $teamId = null): TaxGroup
    {
        return DB::transaction(function () use ($name, $taxIds, $teamId) {
            // Create tax group
            $taxGroup = new TaxGroup();
            $taxGroup->name = $name;
            $taxGroup->team_id = $teamId ?? currentTeamId();
            $taxGroup->save();
            
            // Attach taxes
            $taxGroup->taxes()->attach($taxIds->toArray());
            
            return $taxGroup->refresh();
        });
    }
    
    /**
     * Update tax group taxes
     */
    public function updateTaxGroupTaxes(TaxGroup $taxGroup, Collection $taxIds): TaxGroup
    {
        return DB::transaction(function () use ($taxGroup, $taxIds) {
            // Sync taxes (this will remove old and add new)
            $taxGroup->taxes()->sync($taxIds->toArray());
            
            return $taxGroup->refresh();
        });
    }
    
    /**
     * Get tax groups for team
     */
    public function getTaxGroupsForTeam(?int $teamId = null): Collection
    {
        return TaxGroup::forTeam($teamId)
            ->with('taxes')
            ->orderBy('name')
            ->get();
    }
    
    /**
     * Calculate compound tax amount
     */
    public function calculateCompoundTaxAmount(SafeDecimal $baseAmount, Collection $taxes, bool $compoundMode = false): SafeDecimal
    {
        if (!$compoundMode) {
            return $this->calculateSimpleTaxSum($baseAmount, $taxes);
        }
        
        // Compound mode: each tax applies to amount + previous taxes
        $currentAmount = $baseAmount;
        $totalTax = new SafeDecimal('0.00');
        
        foreach ($taxes as $tax) {
            $taxAmount = $currentAmount->multiply($tax->rate);
            $totalTax = $totalTax->add($taxAmount);
            $currentAmount = $currentAmount->add($taxAmount);
        }
        
        return $totalTax;
    }
    
    /* PROTECTED METHODS - Can be overridden for customization */
    
    /**
     * Calculate simple tax sum (non-compound)
     */
    protected function calculateSimpleTaxSum(SafeDecimal $baseAmount, Collection $taxes): SafeDecimal
    {
        $totalTax = new SafeDecimal('0.00');
        
        foreach ($taxes as $tax) {
            $taxAmount = $this->calculateTaxAmount($baseAmount, $tax);
            $totalTax = $totalTax->add($taxAmount);
        }
        
        return $totalTax;
    }
    
    /**
     * Get tax group by ID with validation
     */
    protected function getTaxGroupById(int $taxGroupId, ?int $teamId = null): TaxGroup
    {
        return TaxGroup::forTeam($teamId)->findOrFail($taxGroupId);
    }
}

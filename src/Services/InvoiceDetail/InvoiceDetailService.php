<?php

namespace Condoedge\Finance\Services\InvoiceDetail;

use Condoedge\Finance\Models\InvoiceDetail;
use Condoedge\Finance\Models\InvoiceDetailTax;
use Condoedge\Finance\Models\Invoice;
use Condoedge\Finance\Models\InvoiceStatusEnum;
use Condoedge\Finance\Models\Tax;
use Condoedge\Finance\Models\Dto\Invoices\CreateOrUpdateInvoiceDetail;
use Condoedge\Finance\Models\Dto\Taxes\UpsertManyTaxDetailDto;
use Condoedge\Finance\Models\Dto\Taxes\UpsertTaxDetailDto;
use Condoedge\Finance\Services\Tax\TaxServiceInterface;
use Condoedge\Finance\Facades\TaxModel;
use Condoedge\Finance\Casts\SafeDecimal;
use Condoedge\Finance\Models\GlAccount;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Kompo\Auth\Models\Teams\PermissionTypeEnum;

/**
 * Invoice Detail Service Implementation
 * 
 * Handles all invoice detail business logic including creation, updates,
 * tax application, calculations, and validation.
 * 
 * This implementation can be easily overridden by binding a custom 
 * implementation to the InvoiceDetailServiceInterface in your service provider.
 */
class InvoiceDetailService implements InvoiceDetailServiceInterface
{
    protected TaxServiceInterface $taxService;
    
    public function __construct(TaxServiceInterface $taxService)
    {
        $this->taxService = $taxService;
    }
    
    /**
     * Create invoice detail with taxes
     */
    public function createInvoiceDetail(CreateOrUpdateInvoiceDetail $dto): InvoiceDetail
    {
        return DB::transaction(function () use ($dto) {
            // Create base detail
            $detail = $this->createBaseDetail($dto);
            
            // Apply taxes if provided
            if (!empty($dto->taxesIds)) {
                $this->applyTaxesToDetail($detail, collect($dto->taxesIds));
            }
            
            return $detail->refresh();
        });
    }
    
    /**
     * Update existing invoice detail
     */
    public function updateInvoiceDetail(CreateOrUpdateInvoiceDetail $dto): InvoiceDetail
    {
        return DB::transaction(function () use ($dto) {
            $detail = InvoiceDetail::findOrFail($dto->id);
            
            // Update detail fields
            $this->updateDetailFields($detail, $dto);
            
            // Update taxes
            $this->applyTaxesToDetail($detail, collect($dto->taxesIds ?? []));
            
            return $detail->refresh();
        });
    }
    
    /**
     * Delete invoice detail
     */
    public function deleteInvoiceDetail(InvoiceDetail $invoiceDetail): bool
    {
        return DB::transaction(function () use ($invoiceDetail) { 
            // Delete associated taxes first
            $invoiceDetail->invoiceTaxes()->delete();
            
            // Delete the detail
            $invoiceDetail->delete();
            
            return true;
        });
    }
    
    /**
     * Apply taxes to detail
     */
    public function applyTaxesToDetail(InvoiceDetail $invoiceDetail, Collection $taxIds): Collection
    {
        return DB::transaction(function () use ($invoiceDetail, $taxIds) {
            // Create/update tax records
            $appliedTaxes = collect();
            
            foreach ($taxIds as $taxId) {
                $taxDetail = $this->upsertTaxForDetail($invoiceDetail, $taxId);
                $appliedTaxes->push($taxDetail);
            }
            
            // Remove taxes that are no longer applied
            $this->removeUnspecifiedTaxes($invoiceDetail, $taxIds);
            
            return $appliedTaxes;
        });
    }
    
    /**
     * Remove taxes from detail
     */
    public function removeTaxesFromDetail(InvoiceDetail $invoiceDetail, Collection $taxIds): bool
    {
        return DB::transaction(function () use ($invoiceDetail, $taxIds) {
            $invoiceDetail->invoiceTaxes()
                ->whereIn('tax_id', $taxIds)
                ->delete();
            
            return true;
        });
    }
    
    /**
     * Calculate extended price
     */
    public function calculateExtendedPrice(int $quantity, SafeDecimal $unitPrice): SafeDecimal
    {
        return $unitPrice->multiply(new SafeDecimal((string) $quantity));
    }
    
    /**
     * Calculate total tax amount
     */
    public function calculateTotalTaxAmount(InvoiceDetail $invoiceDetail): SafeDecimal
    {
        if (!$invoiceDetail->id) {
            \Log::error('Trying to calculate due amount for unsaved invoice detail', [
                'invoice_detail_data' => $invoiceDetail->toArray()
            ]);

            // TODO: We could put a placeholder calculation here
            return new SafeDecimal('0.00');
        }
        
        return $invoiceDetail->sql_tax_amount;
    }
    
    /**
     * Calculate total amount
     */
    public function calculateTotalAmount(InvoiceDetail $invoiceDetail): SafeDecimal
    {
        if (!$invoiceDetail->id) {
            \Log::error('Trying to calculate due amount for unsaved invoice detail', [
                'invoice_detail_data' => $invoiceDetail->toArray()
            ]);

            // TODO: We could put a placeholder calculation here
            return new SafeDecimal('0.00');
        }

        return $invoiceDetail->sql_total_amount;
    }
    
    /**
     * Get detail taxes
     */
    public function getDetailTaxes(InvoiceDetail $invoiceDetail, ?string $taxName = null): Collection
    {
        $query = $invoiceDetail->invoiceTaxes()->with('tax');
        
        if ($taxName) {
            $query->whereHas('tax', function ($q) use ($taxName) {
                $q->where('name', $taxName);
            });
        }
        
        return $query->get();
    }
    
    /**
     * Get invoice taxes
     */
    public function getInvoiceTaxes(Invoice $invoice, ?string $taxName = null): Collection
    {
        $query = InvoiceDetailTax::whereHas('invoiceDetail', function ($q) use ($invoice) {
            $q->where('invoice_id', $invoice->id);
        })->with(['tax', 'invoiceDetail']);
        
        if ($taxName) {
            $query->whereHas('tax', function ($q) use ($taxName) {
                $q->where('name', $taxName);
            });
        }
        
        return $query->get();
    }
    
    /**
     * Copy detail to another invoice
     */
    public function copyDetailToInvoice(InvoiceDetail $sourceDetail, Invoice $targetInvoice): InvoiceDetail
    {
        return DB::transaction(function () use ($sourceDetail, $targetInvoice) {
            // Create new detail with same data
            $newDetail = new InvoiceDetail();
            $newDetail->invoice_id = $targetInvoice->id;
            $newDetail->name = $sourceDetail->name;
            $newDetail->description = $sourceDetail->description;
            $newDetail->revenue_account_id = $sourceDetail->revenue_account_id;
            $newDetail->product_id = $sourceDetail->product_id;
            $newDetail->quantity = $sourceDetail->quantity;
            $newDetail->unit_price = $sourceDetail->unit_price;
            $newDetail->save();
            
            // Copy taxes
            $taxIds = $sourceDetail->invoiceTaxes()->pluck('tax_id');
            if ($taxIds->isNotEmpty()) {
                $this->applyTaxesToDetail($newDetail, $taxIds);
            }
            
            return $newDetail->refresh();
        });
    }
    
    /**
     * Bulk create details
     */
    public function createBulkDetails(Invoice $invoice, Collection $detailsData): Collection
    {
        return DB::transaction(function () use ($invoice, $detailsData) {
            $createdDetails = collect();
            
            foreach ($detailsData as $detailDto) {
                // Ensure invoice_id is set
                $detailDto->invoice_id = $invoice->id;
                
                $detail = $this->createInvoiceDetail($detailDto);
                $createdDetails->push($detail);
            }
            
            return $createdDetails;
        });
    }
    
    /* PROTECTED METHODS - Can be overridden for customization */
    
    /**
     * Create base detail record
     */
    protected function createBaseDetail(CreateOrUpdateInvoiceDetail $dto): InvoiceDetail
    {
        $detail = new InvoiceDetail();
        $detail->invoice_id = $dto->invoice_id;
        $detail->name = $dto->name;
        $detail->description = $dto->description;
        $detail->revenue_account_id = $dto->revenue_account_id ?? GlAccount::getFromLatestSegmentValue($dto->revenue_natural_account_id)->id;
        $detail->product_id = $dto->product_id;
        $detail->quantity = $dto->quantity;
        $detail->unit_price = $dto->unit_price;
        $detail->save();
        
        return $detail;
    }
    
    /**
     * Update detail fields
     */
    protected function updateDetailFields(InvoiceDetail $detail, CreateOrUpdateInvoiceDetail $dto): void
    {
        $detail->name = $dto->name;
        $detail->description = $dto->description;
        $detail->revenue_account_id = $dto->revenue_account_id ?? GlAccount::getFromLatestSegmentValue($dto->revenue_natural_account_id)->id;
        $detail->product_id = $dto->product_id;
        $detail->quantity = $dto->quantity;
        $detail->unit_price = $dto->unit_price;
        $detail->save();
    }
    
    /**
     * Upsert tax for detail
     */
    protected function upsertTaxForDetail(InvoiceDetail $invoiceDetail, int $taxId): InvoiceDetailTax
    {
        // Check if tax already exists for this detail
        $existingTax = InvoiceDetailTax::where('invoice_detail_id', $invoiceDetail->id)
            ->where('tax_id', $taxId)
            ->first();
            
        if ($existingTax) {
            return $existingTax;
        }

        $invoiceDetail->refresh(); // Ensure we have the latest detail state
        
        // Create new tax record
        $tax = Tax::findOrFail($taxId);
        
        $detailTax = new InvoiceDetailTax();
        $detailTax->invoice_detail_id = $invoiceDetail->id;
        $detailTax->tax_id = $tax->id;
        $detailTax->tax_rate = $tax->rate;
        $detailTax->tax_amount = $this->taxService->calculateTaxAmount(
            $invoiceDetail->extended_price,
            $tax
        ); // We'll be overriding this in the database. So it's just a placeholder
        $detailTax->save();
        
        return $detailTax;
    }
    
    /**
     * Remove taxes not in specified list
     */
    protected function removeUnspecifiedTaxes(InvoiceDetail $invoiceDetail, Collection $taxIds): void
    {
        $invoiceDetail->invoiceTaxes()
            ->whereNotIn('tax_id', $taxIds->toArray())
            ->delete();
    }
}

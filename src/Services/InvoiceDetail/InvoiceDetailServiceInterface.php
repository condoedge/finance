<?php

namespace Condoedge\Finance\Services\InvoiceDetail;

use Condoedge\Finance\Models\InvoiceDetail;
use Condoedge\Finance\Models\InvoiceDetailTax;
use Condoedge\Finance\Models\Invoice;
use Condoedge\Finance\Models\Dto\Invoices\CreateOrUpdateInvoiceDetail;
use Condoedge\Finance\Casts\SafeDecimal;
use Condoedge\Finance\Models\Dto\Taxes\UpsertManyTaxDetailDto;
use Condoedge\Finance\Models\Dto\Taxes\UpsertTaxDetailDto;
use Illuminate\Support\Collection;

/**
 * Interface for Invoice Detail Service
 * 
 * This interface allows easy override of invoice detail business logic
 * by implementing this interface in external packages or custom services.
 */
interface InvoiceDetailServiceInterface
{
    /**
     * Create invoice detail with taxes
     * 
     * @param CreateOrUpdateInvoiceDetail $dto
     * @return InvoiceDetail
     * @throws \Exception When validation fails or tax application fails
     */
    public function createInvoiceDetail(CreateOrUpdateInvoiceDetail $dto): InvoiceDetail;
    
    /**
     * Update existing invoice detail with taxes
     * 
     * @param CreateOrUpdateInvoiceDetail $dto
     * @return InvoiceDetail
     * @throws \Exception When detail not found or validation fails
     */
    public function updateInvoiceDetail(CreateOrUpdateInvoiceDetail $dto): InvoiceDetail;
    
    /**
     * Delete invoice detail and associated taxes
     * 
     * @param InvoiceDetail $invoiceDetail
     * @return bool Success status
     * @throws \Exception When detail cannot be deleted
     */
    public function deleteInvoiceDetail(InvoiceDetail $invoiceDetail): bool;
    
    /**
     * Apply taxes to invoice detail
     * 
     * @param InvoiceDetail $invoiceDetail
     * @param Collection<int> $taxIds
     * @return Collection<InvoiceDetailTax>
     */
    public function applyTaxesToDetail(UpsertManyTaxDetailDto $data): Collection;

    public function upsertTaxForDetail(UpsertTaxDetailDto $data): InvoiceDetailTax;
    
    /**
     * Remove specific taxes from invoice detail
     * 
     * @param InvoiceDetail $invoiceDetail
     * @param Collection<int> $taxIds
     * @return bool Success status
     */
    public function removeTaxesFromDetail(InvoiceDetail $invoiceDetail, Collection $taxIds): bool;
    
    /**
     * Calculate extended price (quantity × unit_price)
     * 
     * @param int $quantity
     * @param SafeDecimal $unitPrice
     * @return SafeDecimal
     */
    public function calculateExtendedPrice(int $quantity, SafeDecimal $unitPrice): SafeDecimal;
    
    /**
     * Calculate total tax amount for invoice detail
     * 
     * @param InvoiceDetail $invoiceDetail
     * @return SafeDecimal
     */
    public function calculateTotalTaxAmount(InvoiceDetail $invoiceDetail): SafeDecimal;
    
    /**
     * Calculate total amount (extended_price + tax_amount)
     * 
     * @param InvoiceDetail $invoiceDetail
     * @return SafeDecimal
     */
    public function calculateTotalAmount(InvoiceDetail $invoiceDetail): SafeDecimal;
    
    /**
     * Get all taxes applied to invoice detail
     * 
     * @param InvoiceDetail $invoiceDetail
     * @param string|null $taxName Filter by tax name
     * @return Collection<InvoiceDetailTax>
     */
    public function getDetailTaxes(InvoiceDetail $invoiceDetail, ?string $taxName = null): Collection;
    
    /**
     * Get all taxes for entire invoice
     * 
     * @param Invoice $invoice
     * @param string|null $taxName Filter by tax name
     * @return Collection<InvoiceDetailTax>
     */
    public function getInvoiceTaxes(Invoice $invoice, ?string $taxName = null): Collection;
    
    /**
     * Copy detail from one invoice to another
     * 
     * @param InvoiceDetail $sourceDetail
     * @param Invoice $targetInvoice
     * @return InvoiceDetail
     */
    public function copyDetailToInvoice(InvoiceDetail $sourceDetail, Invoice $targetInvoice): InvoiceDetail;
    
    /**
     * Bulk create invoice details for invoice
     * 
     * @param Invoice $invoice
     * @param Collection<CreateOrUpdateInvoiceDetail> $detailsData
     * @return Collection<InvoiceDetail>
     * @throws \Exception When any detail creation fails
     */
    public function createBulkDetails(Invoice $invoice, Collection $detailsData): Collection;
}

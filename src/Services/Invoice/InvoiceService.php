<?php

namespace Condoedge\Finance\Services\Invoice;

use Condoedge\Finance\Models\Invoice;
use Condoedge\Finance\Models\InvoiceStatusEnum;
use Condoedge\Finance\Models\InvoiceTypeEnum;
use Condoedge\Finance\Models\TaxGroup;
use Condoedge\Finance\Models\Dto\Invoices\CreateInvoiceDto;
use Condoedge\Finance\Models\Dto\Invoices\UpdateInvoiceDto;
use Condoedge\Finance\Models\Dto\Invoices\ApproveInvoiceDto;
use Condoedge\Finance\Models\Dto\Invoices\ApproveManyInvoicesDto;
use Condoedge\Finance\Models\Dto\Invoices\CreateOrUpdateInvoiceDetail;
use Condoedge\Finance\Billing\PaymentGatewayResolver;
use Condoedge\Finance\Services\PaymentGatewayService;
use Condoedge\Finance\Casts\SafeDecimal;
use Condoedge\Finance\Facades\CustomerModel;
use Condoedge\Finance\Facades\InvoiceDetailModel;
use Condoedge\Finance\Facades\PaymentGateway;
use Condoedge\Utils\Facades\GlobalConfig;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Invoice Service Implementation
 * 
 * Handles all invoice business logic including creation, updates, 
 * approval workflows, and calculations.
 * 
 * This implementation can be easily overridden by binding a custom 
 * implementation to the InvoiceServiceInterface in your service provider.
 */
class InvoiceService implements InvoiceServiceInterface
{
    protected PaymentGatewayService $paymentGatewayService;
    
    public function __construct(PaymentGatewayService $paymentGatewayService)
    {
        $this->paymentGatewayService = $paymentGatewayService;
    }

    public function upsertInvoice(CreateInvoiceDto|UpdateInvoiceDto $dto): Invoice
    {
        if ($dto instanceof CreateInvoiceDto) {
            return $this->createInvoice($dto);
        } elseif ($dto instanceof UpdateInvoiceDto) {
            return $this->updateInvoice($dto);
        }
        
        throw new \InvalidArgumentException('Invalid DTO type provided');
    }
    
    /**
     * Create a new invoice with full business logic
     */
    public function createInvoice(CreateInvoiceDto $dto): Invoice
    {
        return DB::transaction(function () use ($dto) {
            // Create base invoice
            $invoice = $this->createBaseInvoice($dto);
            
            // Setup payment gateway integration
            $this->setupInvoicePaymentGateway($invoice);
            
            // Apply customer preferences and data
            $this->applyCustomerDataToInvoice($invoice, $dto->customer_id);
            
            // Save the invoice to get ID for details
            $invoice->save();
            
            // Create invoice details
            $this->createInvoiceDetails($invoice, $dto->invoiceDetails);
            
            // Refresh to get calculated fields
            return $invoice->refresh();
        });
    }
    
    /**
     * Update existing invoice
     */
    public function updateInvoice(UpdateInvoiceDto $dto): Invoice
    {
        return DB::transaction(function () use ($dto) {
            $invoice = Invoice::findOrFail($dto->id);
            
            // Update base fields
            $this->updateInvoiceFields($invoice, $dto);
            
            // Handle invoice details updates/creation
            $this->updateInvoiceDetails($invoice, $dto->invoiceDetails);
            
            return $invoice->refresh();
        });
    }
    
    /**
     * Approve a single invoice
     */
    public function approveInvoice(ApproveInvoiceDto $dto): Invoice
    {
        $invoice = Invoice::findOrFail($dto->invoice_id);
        
        // Apply approval
        $this->applyApprovalToInvoice($invoice);
        
        return $invoice;
    }
    
    /**
     * Approve multiple invoices
     */
    public function approveMany(ApproveManyInvoicesDto $dto): Collection
    {
        return DB::transaction(function () use ($dto) {
            $invoices = Invoice::whereIn('id', $dto->invoices_ids)->get();
            
            // Approve all if validation passes
            foreach ($invoices as $invoice) {
                $this->applyApprovalToInvoice($invoice);
            }
            
            return $invoices;
        });
    }
    
    /**
     * Get default tax IDs for invoice
     */
    public function getDefaultTaxesIds(Invoice $invoice): Collection
    {
        $taxGroupId = $this->resolveTaxGroupId($invoice);
        $taxGroup = TaxGroup::findOrFail($taxGroupId);
        
        return $taxGroup->taxes()->pluck('fin_taxes.id');
    }
    
    /* PROTECTED METHODS - Can be overridden for customization */
    
    /**
     * Create base invoice model
     */
    protected function createBaseInvoice(CreateInvoiceDto $dto): Invoice
    {
        $invoice = new Invoice();
        $invoice->customer_id = $dto->customer_id;
        $invoice->invoice_type_id = $dto->invoice_type_id;
        $invoice->payment_type_id = $dto->payment_type_id;
        $invoice->invoice_date = $dto->invoice_date;
        $invoice->invoice_due_date = $dto->invoice_due_date;
        $invoice->is_draft = $dto->is_draft;
        
        return $invoice;
    }
    
    /**
     * Setup payment gateway for invoice using stateless approach
     */
    protected function setupInvoicePaymentGateway(Invoice $invoice): void
    {
        // Using new stateless approach - no static state required
        $cashAccount = $this->paymentGatewayService->getCashAccountForInvoice($invoice);
        $invoice->account_receivable_id = $cashAccount->id;
    }
    
    /**
     * Apply customer data to invoice
     */
    protected function applyCustomerDataToInvoice(Invoice $invoice, int $customerId): void
    {
        $customer = CustomerModel::find($customerId);
        
        // Fill invoice with customer preferences/data
        $customer->fillInvoiceForCustomer($invoice);
    }
    
    /**
     * Create invoice details from array
     */
    protected function createInvoiceDetails(Invoice $invoice, array $detailsData): void
    {
        foreach ($detailsData as $detail) {
            InvoiceDetailModel::createInvoiceDetail(new CreateOrUpdateInvoiceDetail($detail + [
                'invoice_id' => $invoice->id,
            ]));
        }
    }
    
    /**
     * Update invoice fields
     */
    protected function updateInvoiceFields(Invoice $invoice, UpdateInvoiceDto $dto): void
    {
        $invoice->payment_type_id = $dto->payment_type_id;
        $invoice->invoice_date = $dto->invoice_date;
        $invoice->invoice_due_date = $dto->invoice_due_date;
        $invoice->save();
    }
    
    /**
     * Update/create invoice details
     */
    protected function updateInvoiceDetails(Invoice $invoice, array $detailsData): void
    {
        foreach ($detailsData as $detail) {
            $id = $detail['id'] ?? null;
            
            $data = new CreateOrUpdateInvoiceDetail($detail + [
                'invoice_id' => $invoice->id,
            ]);
            
            if ($id) {
                InvoiceDetailModel::editInvoiceDetail($data);
            } else {
                InvoiceDetailModel::createInvoiceDetail($data);
            }
        }
    }
    
    /**
     * Apply approval to invoice
     */
    protected function applyApprovalToInvoice(Invoice $invoice): void
    {
        $invoice->markApproved();
    }
    
    /**
     * Resolve tax group ID for invoice
     */
    protected function resolveTaxGroupId(Invoice $invoice): int
    {
        return $invoice->customer?->defaultAddress->tax_group_id 
            ?? GlobalConfig::getOrFail('default_tax_group_id');
    }
}

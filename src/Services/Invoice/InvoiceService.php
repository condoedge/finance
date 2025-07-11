<?php

namespace Condoedge\Finance\Services\Invoice;

use Condoedge\Finance\Facades\CustomerModel;
use Condoedge\Finance\Facades\CustomerService;
use Condoedge\Finance\Facades\InvoiceDetailService;
use Condoedge\Finance\Facades\PaymentGateway;
use Condoedge\Finance\Facades\PaymentTermService;
use Condoedge\Finance\Models\Customer;
use Condoedge\Finance\Models\Dto\Invoices\ApproveInvoiceDto;
use Condoedge\Finance\Models\Dto\Invoices\ApproveManyInvoicesDto;
use Condoedge\Finance\Models\Dto\Invoices\CreateInvoiceDto;
use Condoedge\Finance\Models\Dto\Invoices\CreateOrUpdateInvoiceDetail;
use Condoedge\Finance\Models\Dto\Invoices\PayInvoiceDto;
use Condoedge\Finance\Models\Dto\Invoices\UpdateInvoiceDto;
use Condoedge\Finance\Models\Invoice;
use Condoedge\Finance\Models\PaymentTerm;
use Condoedge\Finance\Models\TaxGroup;
use Condoedge\Utils\Facades\GlobalConfig;
use Condoedge\Utils\Models\ContactInfo\Maps\Address;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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

            // Apply customer preferences and data
            $this->applyCustomerDataToInvoice($invoice, $dto->customer_id);

            // Save the invoice to get ID for details
            $invoice->save();

            // Create invoice details
            $this->createInvoiceDetails($invoice, $dto->invoiceDetails);

            if ($invoice->payment_method_id) {
                $this->setupInvoiceAccount($invoice);
            }

            if ($invoice->payment_term_id) {
                PaymentTermService::manageNewPaymentTermIntoInvoice($invoice);
            }

            // Refresh to get calculated fields
            $invoice->refresh();

            return $invoice;
        });
    }

    /**
     * Update existing invoice
     */
    public function updateInvoice(UpdateInvoiceDto $dto): Invoice
    {
        return DB::transaction(function () use ($dto) {
            $invoice = Invoice::findOrFail($dto->id);

            $oldPaymentTermId = $invoice->getOriginal('payment_term_id');

            // Update base fields
            $this->updateInvoiceFields($invoice, $dto);

            // Handle invoice details updates/creation
            if (isset($dto->invoiceDetails)) {
                $this->updateInvoiceDetails($invoice, $dto->invoiceDetails);
            }

            $originalPaymentTerm = PaymentTerm::find($oldPaymentTermId);
            PaymentTermService::manageNewPaymentTermIntoInvoice($invoice, $originalPaymentTerm?->term_type);

            if ($invoice->payment_method_id) {
                $this->setupInvoiceAccount($invoice);
            }

            $invoice->refresh();

            return $invoice;
        });
    }

    // We are already setting the customer address on insert using a trigger.
    // But if the customer doesn't have an address at that moment, we are setting it with this method after.
    public function setAddress(Invoice $invoice, array $addressData): void
    {
        if ($invoice->address && !$invoice->is_draft) {
            throw new Exception('translate.cannot-update-address-on-non-draft-invoice');
        }

        DB::transaction(function () use ($invoice, $addressData) {
            // $invoice->address()->delete(); // Remove existing address if any
            Address::createMainForFromRequest($invoice, $addressData);

            // This is the main customer of the historical customer
            $customer = Customer::find($invoice->customer_id);
            if (!$customer->address) {
                Address::createMainForFromRequest($customer, $addressData);
            }
        });
    }

    /**
     * Approve a single invoice
     */
    public function approveInvoice(ApproveInvoiceDto $dto): Invoice
    {
        return DB::transaction(function () use ($dto) {
            $invoice = Invoice::findOrFail($dto->invoice_id);

            if ($invoice->is_draft) {
                $this->updateInvoice(new UpdateInvoiceDto([
                    'id' => $invoice->id,
                    'payment_method_id' => $invoice->payment_method_id ?? $dto->payment_method_id,
                    'payment_term_id' => $invoice->payment_term_id ?? $dto->payment_term_id,
                ]));
            }

            if ($dto->address) {
                $this->setAddress($invoice, $dto->address->toArray() ?? []);
            }

            // Apply approval
            $this->applyApprovalToInvoice($invoice);

            return $invoice;
        });
    }

    public function payInvoice(PayInvoiceDto $dto): bool
    {
        return DB::transaction(function () use ($dto) {
            $invoice = Invoice::findOrFail($dto->invoice_id);

            if ($invoice->is_draft) {
                $this->approveInvoice(new ApproveInvoiceDto([
                    'invoice_id' => $dto->invoice_id,
                    'payment_method_id' => $dto->payment_method_id,
                    'payment_term_id' => $dto->payment_term_id,
                    'address' => $dto->address?->toArray() ?? null,
                ]));

                $invoice->refresh();
            }

            $paymentGateway = PaymentGateway::getGatewayForInvoice($invoice, [
                'installment_ids' => count($dto->installment_ids ?? []) ? $dto->installment_ids : null,
            ]);

            $isSuccessful = $paymentGateway->executeSale($dto->request_data);

            return $isSuccessful;
        });
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
                $this->approveInvoice(new ApproveInvoiceDto([
                    'invoice_id' => $invoice->id,
                ]));
            }

            return $invoices;
        });
    }

    /**
     * Get default tax IDs for invoice
     */
    public function getDefaultTaxesIds(?Invoice $invoice): Collection
    {
        $taxGroupId = $this->resolveTaxGroupId($invoice);

        if (!$taxGroupId) {
            return collect(); // Return empty collection if no tax group found
        }

        $taxGroup = TaxGroup::findOrFail($taxGroupId);

        return $taxGroup->taxes()->active()->pluck('fin_taxes.id');
    }

    /* PROTECTED METHODS - Can be overridden for customization */

    /**
     * Create base invoice model
     */
    protected function createBaseInvoice(CreateInvoiceDto $dto): Invoice
    {
        $invoice = new Invoice();
        $invoice->customer_id = $dto->customer_id;
        $invoice->invoice_date = $dto->invoice_date;
        $invoice->invoice_type_id = $dto->invoice_type_id;
        $invoice->is_draft = true; // Always it starts as draft
        $invoice->possible_payment_terms = $dto->possible_payment_terms ?? [];
        $invoice->possible_payment_methods = $dto->possible_payment_methods ?? [];
        $invoice->payment_method_id = $dto->payment_method_id ?? (count($invoice->possible_payment_methods) == 1 ? $invoice->possible_payment_methods[0] : null);
        $invoice->payment_term_id = $dto->payment_term_id ?? (count($invoice->possible_payment_terms) == 1 ? $invoice->possible_payment_terms[0] : null);
        $invoice->invoiceable_type = $dto->invoiceable_type;
        $invoice->invoiceable_id = $dto->invoiceable_id;

        if ($invoice->paymentTerm) {
            $invoice->invoice_due_date = $invoice->paymentTerm->calculateDueDate($invoice->invoice_date);
        }

        return $invoice;
    }

    /**
     * Setup payment gateway for invoice using stateless approach
     */
    protected function setupInvoiceAccount(Invoice $invoice): void
    {
        $account = $invoice->payment_method_id->getReceivableAccount();
        $invoice->account_receivable_id = $account?->id;
        $invoice->save();
    }

    /**
     * Apply customer data to invoice
     */
    protected function applyCustomerDataToInvoice(Invoice $invoice, int $customerId): void
    {
        $customer = CustomerModel::find($customerId);

        // Fill invoice with customer preferences/data
        CustomerService::fillInvoiceWithCustomerData($customer, $invoice);
    }

    /**
     * Create invoice details from array
     */
    protected function createInvoiceDetails(Invoice $invoice, array $detailsData): void
    {
        foreach ($detailsData as $detail) {
            InvoiceDetailService::createInvoiceDetail(new CreateOrUpdateInvoiceDetail($detail + [
                'invoice_id' => $invoice->id,
            ]));
        }
    }

    /**
     * Update invoice fields
     */
    protected function updateInvoiceFields(Invoice $invoice, UpdateInvoiceDto $dto): void
    {
        if (!$invoice->is_draft) {
            throw new Exception('translate.cannot-update-non-draft-invoice');
        }

        $invoice->possible_payment_terms = $dto->possible_payment_terms ?? $invoice->possible_payment_terms ?? [];
        $invoice->possible_payment_methods = $dto->possible_payment_methods ?? $invoice->possible_payment_methods ?? [];
        $invoice->payment_term_id = $dto->payment_term_id ?? (count($invoice->possible_payment_terms ?? []) == 1 ? $invoice->possible_payment_terms[0] : ($invoice->isDirty('possible_payment_terms') ? null : $invoice->payment_term_id));
        $invoice->payment_method_id = $dto->payment_method_id ?? (count($invoice->possible_payment_methods ?? []) == 1 ? $invoice->possible_payment_methods[0] : ($invoice->isDirty('possible_payment_methods') ? null : $invoice->payment_method_id));
        $invoice->invoice_date = $dto->invoice_date ?? $invoice->invoice_date;

        if ($invoice->isDirty('payment_term_id') && $invoice->paymentTerm) {
            $invoice->invoice_due_date = $invoice->paymentTerm->calculateDueDate($invoice->invoice_date);
        }

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
                InvoiceDetailService::updateInvoiceDetail($data);
            } else {
                InvoiceDetailService::createInvoiceDetail($data);
            }
        }
    }

    /**
     * Apply approval to invoice
     */
    protected function applyApprovalToInvoice(Invoice $invoice): void
    {
        if ($invoice->invoiceDetails()->count() == 0) {
            throw new Exception('translate.invoice-must-have-at-least-one-detail');
        }

        $invoice->is_draft = false;
        $invoice->approved_by = auth()->user()?->id ?? 1;
        $invoice->approved_at = now();
        $invoice->save();
    }

    /**
     * Resolve tax group ID for invoice
     */
    protected function resolveTaxGroupId(?Invoice $invoice): int
    {
        return $invoice?->customer?->defaultAddress->tax_group_id
            ?? GlobalConfig::getOrFail('default_tax_group_id');
    }
}

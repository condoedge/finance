<?php

namespace Condoedge\Finance\Services\Invoice;

use Condoedge\Finance\Billing\PaymentResult;
use Condoedge\Finance\Models\Dto\Invoices\ApproveInvoiceDto;
use Condoedge\Finance\Models\Dto\Invoices\ApproveManyInvoicesDto;
use Condoedge\Finance\Models\Dto\Invoices\CreateInvoiceDto;
use Condoedge\Finance\Models\Dto\Invoices\PayInvoiceDto;
use Condoedge\Finance\Models\Dto\Invoices\UpdateInvoiceDto;
use Condoedge\Finance\Models\Invoice;
use Illuminate\Support\Collection;

/**
 * Interface for Invoice Service
 *
 * This interface allows easy override of invoice business logic
 * by implementing this interface in external packages or custom services.
 */
interface InvoiceServiceInterface
{
    /**
     * Upsert an invoice based on the provided DTO
     *
     * This method will either create a new invoice or update an existing one
     * based on the type of DTO provided.
     *
     * @param CreateInvoiceDto|UpdateInvoiceDto $dto
     *
     * @return Invoice
     *
     * @throws \Exception When validation fails or business rules are violated
     */
    public function upsertInvoice(CreateInvoiceDto|UpdateInvoiceDto $dto): Invoice;

    /**
     * Create a new invoice from DTO
     *
     * @param CreateInvoiceDto $dto
     *
     * @return Invoice
     *
     * @throws \Exception When validation fails or business rules are violated
     */
    public function createInvoice(CreateInvoiceDto $dto): Invoice;

    /**
     * Update an existing invoice from DTO
     *
     * @param UpdateInvoiceDto $dto
     *
     * @return Invoice
     *
     * @throws \Exception When invoice not found or validation fails
     */
    public function updateInvoice(UpdateInvoiceDto $dto): Invoice;

    public function setAddress(Invoice $invoice, array $addressData): void;

    public function payInvoice(PayInvoiceDto $dto): PaymentResult;

    /**
     * Approve a single invoice
     *
     * @param ApproveInvoiceDto $dto
     *
     * @return Invoice
     *
     * @throws \Exception When invoice cannot be approved
     */
    public function approveInvoice(ApproveInvoiceDto $dto): Invoice;

    /**
     * Approve multiple invoices
     *
     * @param ApproveManyInvoicesDto $dto
     *
     * @return Collection<Invoice>
     *
     * @throws \Exception When any invoice cannot be approved
     */
    public function approveMany(ApproveManyInvoicesDto $dto): Collection;

    /**
     * Get default tax IDs for an invoice
     *
     * @param Invoice $invoice
     *
     * @return Collection<int> Collection of tax IDs
     */
    public function getDefaultTaxesIds(Invoice $invoice): Collection;
}

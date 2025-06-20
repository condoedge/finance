<?php

namespace Condoedge\Finance\Services\Payment;

use Condoedge\Finance\Models\CustomerPayment;
use Condoedge\Finance\Models\Invoice;
use Condoedge\Finance\Models\Customer;
use Condoedge\Finance\Models\Dto\Payments\CreateCustomerPaymentDto;
use Condoedge\Finance\Models\Dto\Payments\CreateCustomerPaymentForInvoiceDto;
use Condoedge\Finance\Models\Dto\Payments\CreateApplyForInvoiceDto;
use Condoedge\Finance\Casts\SafeDecimal;
use Condoedge\Finance\Models\Dto\Payments\CreateAppliesForMultipleInvoiceDto;
use Condoedge\Finance\Models\InvoiceApply;
use Illuminate\Support\Collection;

/**
 * Interface for Payment Service
 * 
 * This interface allows easy override of payment business logic
 * by implementing this interface in external packages or custom services.
 */
interface PaymentServiceInterface
{
    /**
     * Create customer payment
     * 
     * @param CreateCustomerPaymentDto $dto
     * @return CustomerPayment
     * @throws \Exception When payment creation fails
     */
    public function createPayment(CreateCustomerPaymentDto $dto): CustomerPayment;
    
    /**
     * Create payment and apply to invoice atomically
     * 
     * @param CreateCustomerPaymentForInvoiceDto $dto
     * @return CustomerPayment
     * @throws \Exception When payment or application fails
     */
    public function createPaymentAndApplyToInvoice(CreateCustomerPaymentForInvoiceDto $dto): CustomerPayment;
    
    /**
     * Apply existing payment to invoice
     * 
     * @param CreateApplyForInvoiceDto $data
     * @return bool Success status
     * @throws \Exception When application fails or amount exceeds available
     */
    public function applyPaymentToInvoice(CreateApplyForInvoiceDto $data): InvoiceApply;

    /**
     * Create payment application for multiple invoices
     * 
     * @param CreateAppliesForMultipleInvoiceDto $data
     * @return Collection<InvoiceApply>
    */
    public function applyPaymentToInvoices(CreateAppliesForMultipleInvoiceDto $data): Collection;
    
    /**
     * Get payments available for application to invoices
     * 
     * @param Customer|null $customer Filter by customer
     * @return Collection<CustomerPayment>
     */
    public function getAvailablePayments(?Customer $customer = null): Collection;
    
    /**
     * Calculate payment amount left for application
     * 
     * @param CustomerPayment $payment
     * @return SafeDecimal
     */
    public function calculateAmountLeft(CustomerPayment $payment): SafeDecimal;
    
    /**
     * Get payment applications for specific payment
     * 
     * @param CustomerPayment $payment
     * @return Collection Applications
     */
    public function getPaymentApplications(CustomerPayment $payment): Collection;
}

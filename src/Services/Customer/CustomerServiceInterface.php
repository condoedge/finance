<?php

namespace Condoedge\Finance\Services\Customer;

use Condoedge\Finance\Models\Customer;
use Condoedge\Finance\Models\Dto\Customers\CreateCustomerFromCustomable;
use Condoedge\Finance\Models\Dto\Customers\CreateOrUpdateCustomerDto;
use Condoedge\Finance\Models\Invoice;
use Illuminate\Support\Collection;

/**
 * Interface for Customer Service
 *
 * This interface allows easy override of customer business logic
 * by implementing this interface in external packages or custom services.
 */
interface CustomerServiceInterface
{
    /**
     * Create or update customer from DTO
     *
     * @param CreateOrUpdateCustomerDto $dto
     *
     * @return Customer
     *
     * @throws \Exception When validation fails or business rules are violated
     */
    public function createOrUpdate(CreateOrUpdateCustomerDto $dto): Customer;

    /**
     * Create customer from customable model
     *
     * @param CreateCustomerFromCustomable $dto
     *
     * @return Customer
     *
     * @throws \Exception When customable validation fails
     */
    public function createFromCustomable(CreateCustomerFromCustomable $dto): Customer;

    /**
     * Set default billing address for customer
     *
     * @param Customer $customer
     * @param int $addressId
     *
     * @return Customer
     *
     * @throws \Exception When address doesn't belong to customer
     */
    public function setDefaultAddress(Customer $customer, int $addressId): Customer;

    /**
     * Fill invoice with customer data and preferences
     *
     * @param Customer $customer
     * @param Invoice $invoice
     *
     * @return Invoice
     *
     * @throws \Exception When customer data is incomplete
     */
    public function fillInvoiceWithCustomerData(Customer $customer, Invoice $invoice): Invoice;

    /**
     * Validate and get available customable models
     *
     * @return Collection<string> Collection of class names
     *
     * @throws \Exception When customable model doesn't implement required contract
     */
    public function getValidCustomableModels(): Collection;

    /**
     * Get customer due amount calculation
     *
     * @param Customer $customer
     *
     * @return \Condoedge\Finance\Casts\SafeDecimal
     */
    public function calculateDueAmount(Customer $customer): \Condoedge\Finance\Casts\SafeDecimal;

    /**
     * Sync customer data with related customable model
     *
     * @param Customer $customer
     *
     * @return bool Success status
     */
    public function syncWithCustomable(Customer $customer): bool;
}

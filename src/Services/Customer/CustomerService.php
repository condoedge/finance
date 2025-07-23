<?php

namespace Condoedge\Finance\Services\Customer;

use Condoedge\Finance\Casts\SafeDecimal;
use Condoedge\Finance\Models\CustomableContract;
use Condoedge\Finance\Models\Customer;
use Condoedge\Finance\Models\Dto\Customers\CreateCustomerFromCustomable;
use Condoedge\Finance\Models\Dto\Customers\CreateOrUpdateCustomerDto;
use Condoedge\Finance\Models\Invoice;
use Condoedge\Utils\Models\ContactInfo\Maps\Address;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Customer Service Implementation
 *
 * Handles all customer business logic including creation, updates,
 * address management, and invoice integration.
 *
 * This implementation can be easily overridden by binding a custom
 * implementation to the CustomerServiceInterface in your service provider.
 */
class CustomerService implements CustomerServiceInterface
{
    /**
     * Create or update customer from DTO
     */
    public function createOrUpdate(CreateOrUpdateCustomerDto $dto): Customer
    {
        return DB::transaction(function () use ($dto) {
            if (isset($dto->id)) {
                return $this->updateExistingCustomer($dto);
            }

            return $this->createNewCustomer($dto);
        });
    }

    /**
     * Create customer from customable model
     */
    public function createFromCustomable(CreateCustomerFromCustomable $dto): Customer
    {
        return DB::transaction(function () use ($dto) {
            // Create/update customer from customable
            $customer = $this->upsertCustomerFromCustomable($dto);

            // Setup address if provided
            if ($dto->address) {
                $this->createAddressForCustomer($customer, $dto->address->toArray());
            }

            return $customer->refresh();
        });
    }

    /**
     * Set default address for customer
     */
    public function setDefaultAddress(Customer $customer, int $addressId): Customer
    {
        return DB::transaction(function () use ($customer, $addressId) {
            // Update default address
            $customer->default_billing_address_id = $addressId;
            $customer->save();

            return $customer->refresh();
        });
    }

    /**
     * Fill invoice with customer data
     */
    public function fillInvoiceWithCustomerData(Customer $customer, Invoice $invoice): Invoice
    {
        // Apply customer data to invoice
        $invoice->customer_id = $customer->id;

        return $invoice;
    }

    /**
     * Get validated customable models
     */
    public function getValidCustomableModels(): Collection
    {
        $customables = collect(config('kompo-finance.customable_models'));

        // Validate each customable implements required contract
        $customables->each(function ($customable) {
            $this->validateCustomableImplementsContract($customable);
        });

        return $customables;
    }

    /**
     * Calculate customer due amount
     */
    public function calculateDueAmount(Customer $customer): SafeDecimal
    {
        if (!$customer->id) {
            Log::error('Trying to calculate due amount for unsaved customer', [
                'customer_data' => $customer->toArray()
            ]);

            // TODO: We could put a placeholder calculation here
            return new SafeDecimal('0.00');
        }

        return new SafeDecimal($customer->sql_customer_due_amount ?? '0.00');
    }

    /**
     * Sync customer with customable
     */
    public function syncWithCustomable(Customer $customer): bool
    {
        $customable = $customer->customable()->first();

        if (!$customable) {
            return false;
        }

        try {
            $customable->updateFromCustomer($customer);
            return true;
        } catch (\Exception $e) {
            // Log error but don't throw - sync is not critical
            \Log::error('Failed to sync customer with customable', [
                'customer_id' => $customer->id,
                'customable_type' => get_class($customable),
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    public function ensureCustomerFromTeam(Customer $customer, $teamId)
    {
        if ($customer->team_id == $teamId) {
            return $customer;
        }

        $customer = Customer::equalButAnotherTeam($customer, $teamId)->first() ?? $customer->clone($teamId);

        return $customer;
    }

    /* PROTECTED METHODS - Can be overridden for customization */

    /**
     * Update existing customer
     */
    protected function updateExistingCustomer(CreateOrUpdateCustomerDto $dto): Customer
    {
        $customer = Customer::findOrFail($dto->id);
        $customer->name = $dto->name;
        $customer->email = $dto->email;
        $customer->phone = $dto->phone;
        $customer->save();

        return $customer;
    }

    /**
     * Create new customer
     */
    protected function createNewCustomer(CreateOrUpdateCustomerDto $dto): Customer
    {
        $customer = new Customer();
        $customer->name = $dto->name;
        $customer->email = $dto->email;
        $customer->phone = $dto->phone;
        $customer->team_id = $dto->team_id ?? currentTeamId();
        $customer->save();

        // Create address if provided
        if ($dto->address) {
            $this->createAddressForCustomer($customer, $dto->address->toArray());
        }

        return $customer;
    }

    /**
     * Create/update customer from customable
     */
    protected function upsertCustomerFromCustomable(CreateCustomerFromCustomable $dto): Customer
    {
        return $dto->customable->upsertCustomerFromThisModel();
    }

    /**
     * Create address for customer
     */
    protected function createAddressForCustomer(Customer $customer, array $addressData): void
    {
        Address::createMainForFromRequest($customer, $addressData);
    }

    /**
     * Validate customable model implements required contract
     */
    protected function validateCustomableImplementsContract(string $customableClass): void
    {
        if (!in_array(CustomableContract::class, class_implements($customableClass), true)) {
            throw new \Exception(__('finance-customable-model-must-implement', ['model' => $customableClass]));
        }
    }
}

<?php

namespace Condoedge\Finance\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Customer Service Facade
 * 
 * @method static \Condoedge\Finance\Models\Customer createOrUpdate(\Condoedge\Finance\Models\Dto\Customers\CreateOrUpdateCustomerDto $dto)
 * @method static \Condoedge\Finance\Models\Customer createFromCustomable(\Condoedge\Finance\Models\Dto\Customers\CreateCustomerFromCustomable $dto)
 * @method static \Condoedge\Finance\Models\Customer setDefaultAddress(\Condoedge\Finance\Models\Customer $customer, int $addressId)
 * @method static \Condoedge\Finance\Models\Invoice fillInvoiceWithCustomerData(\Condoedge\Finance\Models\Customer $customer, \Condoedge\Finance\Models\Invoice $invoice)
 * @method static \Illuminate\Support\Collection getValidCustomableModels()
 * @method static \Condoedge\Finance\Casts\SafeDecimal calculateDueAmount(\Condoedge\Finance\Models\Customer $customer)
 * @method static bool syncWithCustomable(\Condoedge\Finance\Models\Customer $customer)
 * 
 * @see \Condoedge\Finance\Services\Customer\CustomerServiceInterface
 */
class CustomerService extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Condoedge\Finance\Services\Customer\CustomerServiceInterface::class;
    }
}

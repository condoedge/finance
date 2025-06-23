<?php

namespace Condoedge\Finance\Http\Controllers\Api;

use Condoedge\Finance\Facades\CustomerModel;
use Condoedge\Finance\Facades\CustomerService;
use Condoedge\Finance\Models\Dto\Customers\CreateCustomerFromCustomable;
use Condoedge\Finance\Models\Dto\Customers\CreateOrUpdateCustomerDto;
use Illuminate\Routing\Controller;

class CustomersController extends Controller
{
    /**
     * @operationId Create or update customer
     */
    public function saveCustomer(CreateOrUpdateCustomerDto $data)
    {
        CustomerService::createOrUpdate($data);

        return response()->json([
            'message' => __('translate.customer-created'),
        ]);
    }

    /**
     * @operationId Create from another model
     */
    public function createFromCustomableModel(CreateCustomerFromCustomable $data)
    {
        CustomerService::createFromCustomable($data);

        return response()->json([
            'message' => __('translate.customer-created'),
        ]);
    }
}
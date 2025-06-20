<?php

namespace Condoedge\Finance\Http\Controllers\Api;

use Condoedge\Finance\Facades\CustomerModel;
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
        CustomerModel::createOrEditFromDto($data);

        return response()->json([
            'message' => __('finance-customer-created'),
        ]);
    }

    /**
     * @operationId Create from another model
     */
    public function createFromCustomableModel(CreateCustomerFromCustomable $data)
    {
        CustomerModel::createOrEditFromCustomable($data);

        return response()->json([
            'message' => __('finance-customer-created'),
        ]);
    }
}
<?php

namespace Condoedge\Finance\Http;

use Condoedge\Finance\Facades\CustomerModel;
use Condoedge\Finance\Models\Dto\CreateOrUpdateCustomerDto;
use Condoedge\Finance\Models\Dto\CreateCustomerFromCustomable;
use Dedoc\Scramble\Attributes\BodyParameter;
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
            'message' => __('translate.customer-created'),
        ]);
    }

    /**
     * @operationId Create from another model
     */
    public function createFromCustomableModel(CreateCustomerFromCustomable $data)
    {
        CustomerModel::createOrEditFromCustomable($data);

        return response()->json([
            'message' => __('translate.customer-created'),
        ]);
    }
}
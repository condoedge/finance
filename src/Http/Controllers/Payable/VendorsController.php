<?php

namespace Condoedge\Finance\Http\Controllers\Payable;

use Condoedge\Finance\Facades\VendorModel;
use Condoedge\Finance\Models\Dto\Vendors\CreateVendorFromCustomable;
use Condoedge\Finance\Models\Dto\Vendors\CreateOrUpdateVendorDto;
use Illuminate\Routing\Controller;

class VendorsController extends Controller
{
    /**
     * @operationId Create or update vendor
     */
    public function saveVendor(CreateOrUpdateVendorDto $data)
    {
        VendorModel::createOrEditFromDto($data);

        return response()->json([
            'message' => __('translate.vendor-created'),
        ]);
    }

    /**
     * @operationId Create from another model
     */
    public function createFromCustomableModel(CreateVendorFromCustomable $data)
    {
        VendorModel::createOrEditFromCustomable($data);

        return response()->json([
            'message' => __('translate.vendor-created'),
        ]);
    }
}

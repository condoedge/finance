<?php

namespace Condoedge\Finance\Http\Controllers\Payable;

use Condoedge\Finance\Facades\VendorPaymentModel;
use Condoedge\Finance\Models\Dto\Payments\CreateVendorPaymentDto;
use Condoedge\Finance\Models\Dto\Payments\CreateVendorPaymentForBillDto;
use Illuminate\Routing\Controller;

class VendorPaymentsController extends Controller
{
    /**
     * @operationId Create vendor payment
     */
    public function createVendorPayment(CreateVendorPaymentDto $data)
    {
        VendorPaymentModel::createForVendor($data);

        return response()->json([
            'message' => __('translate.vendor-payment-created'),
        ]);
    }

    /**
     * @operationId Create and apply vendor payment
     */
    public function createVendorPaymentForBill(CreateVendorPaymentForBillDto $data)
    {
        VendorPaymentModel::createForVendorAndApply($data);

        return response()->json([
            'message' => __('translate.vendor-payment-created-for-bill'),
        ]);
    }
}

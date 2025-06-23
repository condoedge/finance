<?php

namespace Condoedge\Finance\Http\Controllers\Api;

use Condoedge\Finance\Facades\PaymentService;
use Condoedge\Finance\Models\Dto\Payments\CreateCustomerPaymentDto;
use Condoedge\Finance\Models\Dto\Payments\CreateCustomerPaymentForInvoiceDto;
use Illuminate\Routing\Controller;

class PaymentsController extends Controller
{
    /**
     * @operationId Create payment
     */
    public function createCustomerPayment(CreateCustomerPaymentDto $data)
    {
        PaymentService::createPayment($data);

        return response()->json([
            'message' => __('translate.payment-created'),
        ]);
    }

    /**
     * @operationId Create and apply payment
     */
    public function createCustomerPaymentForInvoice(CreateCustomerPaymentForInvoiceDto $data)
    {
        PaymentService::createPaymentAndApplyToInvoice($data);

        return response()->json([
            'message' => __('translate.payment-created-for-invoice'),
        ]);
    }
}
<?php

namespace Condoedge\Finance\Http;

use Condoedge\Finance\Facades\CustomerPaymentModel;
use Condoedge\Finance\Models\Dto\CreateCustomerPaymentDto;
use Condoedge\Finance\Models\Dto\CreateCustomerPaymentForInvoiceDto;
use Illuminate\Routing\Controller;

class PaymentsController extends Controller
{
    /**
     * @operationId Create payment
     */
    public function createCustomerPayment(CreateCustomerPaymentDto $data)
    {
        CustomerPaymentModel::createForCustomer($data);

        return response()->json([
            'message' => __('translate.payment-created'),
        ]);
    }

    /**
     * @operationId Create and apply payment
     */
    public function createCustomerPaymentForInvoice(CreateCustomerPaymentForInvoiceDto $data)
    {
        CustomerPaymentModel::createForCustomerAndApply($data);

        return response()->json([
            'message' => __('translate.payment-created-for-invoice'),
        ]);
    }
}
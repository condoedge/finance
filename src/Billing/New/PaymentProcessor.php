<?php

use Condoedge\Finance\Facades\PaymentService;
use Condoedge\Finance\Models\Dto\Payments\CreateCustomerPaymentDto;
use Illuminate\Support\Facades\Log;

class PaymentProcessor implements PaymentProcessorInterface
{
    public function processPayment(PaymentContext $context)
    {
        $gateway = PaymentGatewayResolver::resolveForContext($context);
        $response = $gateway->processPayment($context);
        $payable = $context->payable;

        if (!($payable instanceof FinantialPayableInterface)) {
            Log::critical('Error finishing payment', ['payable_type' => get_class($payable), 'payable_id' => $payable->getPayableId()]);
            throw new \RuntimeException('Unsupported payable type');
        }

        if ($response->success) {
            $payment = PaymentService::createPayment(new CreateCustomerPaymentDto([
                'payment_date' => now(),
                'amount' => $response->amount,
                'customer_id' => $payable->getCustomer()->id,
                'external_reference' => $response->transactionId,
            ]));

            $payable->onPaymentSuccess($payment);
        } else {
            Log::error('Payment failed', [
                'error' => $response->error,
                'transaction_id' => $response->transactionId,
            ]);

            $payable->onPaymentFailed([
                'error' => $response->error,
                'transaction_id' => $response->transactionId,
            ]);
        }
    }
}
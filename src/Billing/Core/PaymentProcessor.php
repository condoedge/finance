<?php

namespace Condoedge\Finance\Billing\Core;

use Condoedge\Finance\Billing\Contracts\FinancialPayableInterface;
use Condoedge\Finance\Billing\Contracts\PaymentProcessorInterface;
use Condoedge\Finance\Billing\Exceptions\PaymentProcessingException;
use Condoedge\Finance\Facades\PaymentGatewayResolver;
use Condoedge\Finance\Facades\PaymentService;
use Condoedge\Finance\Models\Dto\Payments\CreateCustomerPaymentDto;
use Condoedge\Finance\Models\PaymentTrace;
use Condoedge\Finance\Models\PaymentTraceStatusEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Kompo\Elements\BaseElement;

class PaymentProcessor implements PaymentProcessorInterface
{
    public function managePaymentResult(PaymentResult $result, PaymentContext $context)
    {
        try {
            if ($result->isPending) {
                $this->managePaymentTrace($context, $result, PaymentTraceStatusEnum::PROCESSING);

                return $result;
            }

            $payable = $context->payable;

            if (!($payable instanceof FinancialPayableInterface)) {
                Log::critical('Error finishing payment', ['payable_type' => get_class($payable), 'payable_id' => $payable->getPayableId()]);
                throw new \RuntimeException('Unsupported payable type');
            }

            if ($result->success) {
                $paymentTrace = $this->managePaymentTrace($context, $result, PaymentTraceStatusEnum::COMPLETED);

                $payment = PaymentService::createPayment(new CreateCustomerPaymentDto([
                    'payment_date' => now(),
                    'amount' => $result->amount,
                    'customer_id' => $payable->getCustomer()->id,
                    'payment_trace_id' => $paymentTrace->id,
                ]));

                $payable->onPaymentSuccess($payment);
            } else {
                $this->managePaymentTrace($context, $result, PaymentTraceStatusEnum::FAILED);

                Log::error('Payment failed', [
                    'error' => $result->errorMessage,
                    'transaction_id' => $result->transactionId,
                ]);

                $payable->onPaymentFailed([
                    'error' => $result->errorMessage,
                    'transaction_id' => $result->transactionId,
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('Payment processing failed', ['error' => $e->getMessage()]);
            throw new PaymentProcessingException($context, $result, __('error-payment-processing-failed'), $e);
        }
    }

    public function processPayment(PaymentContext $context)
    {
        return DB::transaction(function () use ($context) {
            $result = null;

            try {
                $gateway = PaymentGatewayResolver::resolve($context);
                $result = $gateway->processPayment($context);

                return $this->managePaymentResult($result, $context);
            } catch (\Exception $e) {
                Log::error('Payment processing failed', ['error' => $e->getMessage(), 'context' => $context, 'result' => $result]);
                throw new PaymentProcessingException($context, $result, __('finance-payment-processing-failed'), $e);
            }
        });
    }

    public function getPaymentForm(PaymentContext $context): ?BaseElement
    {
        $gateway = PaymentGatewayResolver::resolve($context);
        return $gateway->getPaymentForm($context);
    }

    protected function managePaymentTrace(PaymentContext $context, PaymentResult $result, PaymentTraceStatusEnum $status): PaymentTrace
    {
        return PaymentTrace::createOrUpdateTrace(
            $result->transactionId,
            $status,
            $context->payable->getPayableId(),
            $context->payable->getPayableType(),
            $result->paymentProviderCode,
            $context->paymentMethod->value
        );
    }
}

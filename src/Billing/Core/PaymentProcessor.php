<?php

namespace Condoedge\Finance\Billing\Core;

use Condoedge\Finance\Billing\Contracts\FinancialPayableInterface;
use Condoedge\Finance\Billing\Contracts\PaymentGatewayInterface;
use Condoedge\Finance\Billing\Contracts\PaymentGatewayResolverInterface;
use Condoedge\Finance\Billing\Contracts\PaymentProcessorInterface;
use Condoedge\Finance\Billing\Contracts\ProviderHealthCheckerInterface;
use Condoedge\Finance\Billing\Exceptions\NoProviderAvailableException;
use Condoedge\Finance\Billing\Exceptions\PaymentProcessingException;
use Condoedge\Finance\Facades\PaymentService;
use Condoedge\Finance\Models\Dto\Payments\CreateCustomerPaymentDto;
use Condoedge\Finance\Models\PaymentTrace;
use Condoedge\Finance\Models\PaymentTraceStatusEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Orchestrates a payment attempt across the dynamic provider chain.
 *
 * processPayment() walks resolveChain() and tries each provider until one
 * succeeds. Fallback rules (audit §1.1.3, design §4.2):
 *   - On PERMANENT error (card declined): stop immediately. Customer's card,
 *     not the provider's fault.
 *   - On any other error: log, record health, try next provider.
 *   - On exhaustion: throw PaymentProcessingException wrapping the last
 *     provider's error, with all attempted-provider context.
 *
 * Each attempt records a fin_payment_traces row with team_id, provider code,
 * reason code, latency, retry count — making "which provider is failing for
 * which team this week" queryable.
 */
class PaymentProcessor implements PaymentProcessorInterface
{
    public function __construct(
        private PaymentGatewayResolverInterface $resolver,
        private ProviderHealthCheckerInterface $healthChecker,
    ) {
    }

    public function managePaymentResult(PaymentResult $result, PaymentContext $context)
    {
        try {
            if ($result->isPending) {
                $this->managePaymentTrace($context, $result, PaymentTraceStatusEnum::PROCESSING);
                return $result;
            }

            $payable = $context->payable;

            if (!($payable instanceof FinancialPayableInterface)) {
                Log::critical('Error finishing payment', [
                    'payable_type' => get_class($payable),
                    'payable_id' => $payable->getPayableId(),
                ]);
                throw new \RuntimeException('Unsupported payable type');
            }

            if ($result->success) {
                $paymentTrace = $this->managePaymentTrace($context, $result, PaymentTraceStatusEnum::COMPLETED);

                $payment = PaymentService::createPayment(new CreateCustomerPaymentDto([
                    'payment_date' => now(),
                    'amount' => $result->amount,
                    'customer_id' => $payable->getCustomer()->id,
                    'payment_trace_id' => $paymentTrace->id,
                    'processor_fees' => $result->processorFees,
                ]));

                try {
                    $payable->onPaymentSuccess($payment);
                } catch (\Exception $e) {
                    Log::critical('Error executing onPaymentSuccess', [
                        'error' => $e->getMessage(),
                        'payable_type' => get_class($payable),
                        'payable_id' => $payable->getPayableId(),
                    ]);
                }
            } else {
                $this->managePaymentTrace($context, $result, PaymentTraceStatusEnum::FAILED);

                PaymentLog::failure(
                    context: $context,
                    providerCode: $result->paymentProviderCode,
                    classification: null,
                    message: $result->errorMessage,
                    latencyMs: 0,
                );

                $payable->onPaymentFailed([
                    'error' => $result->errorMessage,
                    'transaction_id' => $result->transactionId,
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('Payment processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new PaymentProcessingException(
                $context,
                $result,
                __('error-payment-processing-failed'),
                $e,
                $result->paymentProviderCode ?? null,
            );
        }
    }

    public function processPayment(PaymentContext $context)
    {
        return DB::transaction(function () use ($context) {
            return $this->attemptChain($context);
        });
    }

    /**
     * Walk the resolver chain. The first PERMANENT error (card declined) ends
     * the loop; everything else triggers fallback to the next provider.
     */
    private function attemptChain(PaymentContext $context): PaymentResult
    {
        try {
            $chain = $this->resolver->resolveChain($context);
        } catch (NoProviderAvailableException $e) {
            PaymentLog::unavailable($context, $e->reason);
            throw new PaymentProcessingException(
                $context,
                null,
                __('finance-payment-system-unavailable'),
                $e,
            );
        }

        $chainArray = is_array($chain) ? $chain : iterator_to_array($chain);
        $errors = [];
        $retryCount = 0;
        $lastResult = null;
        $previousProvider = null;

        foreach ($chainArray as $provider) {
            /** @var PaymentGatewayInterface $provider */
            $code = $provider->getCode();

            if ($previousProvider !== null) {
                PaymentLog::fallback(
                    context: $context,
                    fromProvider: $previousProvider,
                    toProvider: $code,
                    reason: $errors[count($errors) - 1] ?? null,
                );
            }

            $start = (int) (microtime(true) * 1000);
            PaymentLog::attempt($context, $code, 'charge');

            try {
                $result = $provider->processPayment($context);
                $latencyMs = (int) (microtime(true) * 1000) - $start;

                if ($result->success || $result->isPending) {
                    PaymentLog::success($context, $code, $result->transactionId, $latencyMs);
                    $this->healthChecker->record($code, $context->getTeamId(), PaymentOutcome::success($latencyMs));
                    $this->recordTraceWithMetrics($context, $result, $latencyMs, $retryCount, null);
                    return $this->managePaymentResult($result, $context);
                }

                // PaymentResult::failed — provider returned a failure result (not exception)
                $classification = ErrorClassification::permanent(
                    reasonCode: 'declined',
                    message: $result->errorMessage,
                );
                $errors[] = $classification;
                $lastResult = $result;
                PaymentLog::failure($context, $code, $classification, $result->errorMessage, $latencyMs);
                $this->healthChecker->record($code, $context->getTeamId(), PaymentOutcome::failure($classification, $latencyMs));
                $this->recordTraceWithMetrics($context, $result, $latencyMs, $retryCount, $classification->reasonCode);

                // Provider returned a "soft" failure — surface to user. Don't fallback for declines.
                return $this->managePaymentResult($result, $context);
            } catch (\Throwable $e) {
                $latencyMs = (int) (microtime(true) * 1000) - $start;
                $classification = $provider->classifyError($e);
                $errors[] = $classification;

                PaymentLog::failure($context, $code, $classification, $e->getMessage(), $latencyMs);
                $this->healthChecker->record($code, $context->getTeamId(), PaymentOutcome::failure($classification, $latencyMs));
                $this->recordExceptionTrace($context, $code, $classification, $latencyMs, $retryCount);

                if (!$classification->shouldFallback()) {
                    // Customer-side failure — surface and stop.
                    throw new PaymentProcessingException(
                        $context,
                        null,
                        $e->getMessage(),
                        $e,
                        $code,
                    );
                }

                $previousProvider = $code;
                $retryCount++;
                // continue loop — try next provider
            }
        }

        // Exhausted the chain without success.
        throw new PaymentProcessingException(
            $context,
            $lastResult,
            __('finance-payment-processing-failed'),
            NoProviderAvailableException::allFailed(
                $context->getTeamId(),
                $context->paymentMethod,
                array_map(fn ($c) => $c->reasonCode, $errors),
            ),
        );
    }

    private function managePaymentTrace(PaymentContext $context, PaymentResult $result, PaymentTraceStatusEnum $status): PaymentTrace
    {
        $trace = PaymentTrace::createOrUpdateTrace(
            $result->transactionId,
            $status,
            $context->payable->getPayableId(),
            $context->payable->getPayableType(),
            $result->paymentProviderCode,
            $context->paymentMethod->value,
        );

        // Attribute every trace to its team — webhook-driven flows (Stripe success
        // notifications, BNA webhooks) call managePaymentTrace through
        // managePaymentResult without going through recordTraceWithMetrics, so the
        // team_id column has to be populated here too. Without this, webhook-source
        // traces would land with team_id=NULL and break "failures per team" queries.
        if ($trace->team_id === null && ($teamId = $context->getTeamId())) {
            $trace->team_id = $teamId;
            $trace->save();
        }

        return $trace;
    }

    private function recordTraceWithMetrics(
        PaymentContext $context,
        PaymentResult $result,
        int $latencyMs,
        int $retryCount,
        ?string $reasonCode,
    ): void {
        $trace = $this->managePaymentTrace(
            $context,
            $result,
            $result->success ? PaymentTraceStatusEnum::COMPLETED : PaymentTraceStatusEnum::FAILED,
        );

        // Metric columns from the audit-driven extension migration.
        $trace->latency_ms = $latencyMs;
        $trace->retry_count = $retryCount;
        $trace->failure_reason_code = $reasonCode;
        $trace->save();
    }

    private function recordExceptionTrace(
        PaymentContext $context,
        string $providerCode,
        ErrorClassification $classification,
        int $latencyMs,
        int $retryCount,
    ): void {
        // No transactionId from provider — synthesize one so the trace row has
        // a primary identifier for joins.
        $synthId = 'exc-' . uniqid('', true);
        $trace = PaymentTrace::createOrUpdateTrace(
            $synthId,
            PaymentTraceStatusEnum::FAILED,
            $context->payable->getPayableId(),
            $context->payable->getPayableType(),
            $providerCode,
            $context->paymentMethod->value,
        );

        $trace->team_id = $context->getTeamId();
        $trace->latency_ms = $latencyMs;
        $trace->retry_count = $retryCount;
        $trace->failure_reason_code = $classification->reasonCode;
        $trace->save();
    }
}

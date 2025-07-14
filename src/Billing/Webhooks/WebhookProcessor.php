<?php

namespace Condoedge\Finance\Billing\Webhooks;

use Condoedge\Finance\Billing\PaymentContext;
use Condoedge\Finance\Billing\PaymentResult;
use Condoedge\Finance\Facades\PaymentProcessor;
use Condoedge\Finance\Models\PaymentMethodEnum;
use Condoedge\Finance\Models\PaymentTrace;
use Condoedge\Finance\Models\PaymentTraceStatusEnum;
use Condoedge\Finance\Models\WebhookEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

abstract class WebhookProcessor
{
    /**
     * Process incoming webhook request
     */
    public function handle(Request $request)
    {
        $webhookId = $this->extractWebhookId($request);

        // Check for duplicate processing using cache lock
        $lockKey = "webhook:processing:{$this->getProviderCode()}:{$webhookId}";
        $lock = Cache::lock($lockKey, 30); // 30 second lock

        if (!$lock->get()) {
            Log::info('Webhook already being processed', [
                'provider' => $this->getProviderCode(),
                'webhook_id' => $webhookId
            ]);
            return response()->json(['message' => 'Already processing'], 200);
        }

        try {
            // Verify webhook signature
            if (!$this->verifySignature($request)) {
                Log::warning('Invalid webhook signature', [
                    'provider' => $this->getProviderCode(),
                    'webhook_id' => $webhookId
                ]);
                return response()->json(['error' => 'Invalid signature'], 401);
            }

            // Check if already processed
            if ($this->isAlreadyProcessed($webhookId)) {
                Log::info('Webhook already processed', [
                    'provider' => $this->getProviderCode(),
                    'webhook_id' => $webhookId
                ]);
                return response()->json(['message' => 'Already processed'], 200);
            }

            // Process the webhook
            $result = DB::transaction(function () use ($request, $webhookId) {
                // Record the webhook
                $this->recordWebhook($webhookId, $request->all());

                // Process based on event type
                return $this->processWebhookEvent($request);
            });

            return $result;

        } catch (\Exception $e) {
            Log::error('Webhook processing failed', [
                'provider' => $this->getProviderCode(),
                'webhook_id' => $webhookId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Return success to prevent retries for non-recoverable errors
            if ($this->isNonRecoverableError($e)) {
                return response()->json(['message' => 'Acknowledged'], 200);
            }

            // Return error for recoverable errors to trigger retry
            return response()->json(['error' => 'Processing failed'], 500);

        } finally {
            $lock->release();
        }
    }

    /**
     * Process payment success webhook
     */
    protected function processPaymentSuccess(
        string $transactionId,
        float $amount,
        array $metadata,
        array $additionalData = []
    ) {
        // Extract payable information
        $payableId = $metadata['payable_id'] ?? null;
        $payableType = $metadata['payable_type'] ?? null;
        $paymentMethodId = $metadata['payment_method_id'] ?? null;

        if (!$payableId || !$payableType || !$paymentMethodId) {
            throw new \InvalidArgumentException('Missing required payment metadata');
        }

        // Load the payable
        $payable = $this->loadPayable($payableType, $payableId);
        if (!$payable) {
            throw new \RuntimeException("Payable not found: {$payableType}#{$payableId}");
        }

        // Create payment result
        $paymentResult = PaymentResult::success(
            transactionId: $transactionId,
            amount: $amount,
            paymentProviderCode: $this->getProviderCode(),
            metadata: array_merge($metadata, $additionalData)
        );

        // Create payment context
        $paymentContext = new PaymentContext(
            payable: $payable,
            paymentMethod: PaymentMethodEnum::from($paymentMethodId),
            metadata: $metadata
        );

        // Process the payment
        return PaymentProcessor::managePaymentResult($paymentResult, $paymentContext);
    }

    /**
     * Process payment failure webhook
     */
    protected function processPaymentFailure(
        string $transactionId,
        string $errorMessage,
        array $metadata,
        array $additionalData = []
    ) {
        // Update payment trace if exists
        $trace = PaymentTrace::where('external_transaction_ref', $transactionId)->first();
        if ($trace) {
            $trace->update(['status' => PaymentTraceStatusEnum::FAILED->value]);
        }

        // Extract payable information
        $payableId = $metadata['payable_id'] ?? null;
        $payableType = $metadata['payable_type'] ?? null;

        if ($payableId && $payableType) {
            $payable = $this->loadPayable($payableType, $payableId);
            if ($payable && method_exists($payable, 'onPaymentFailed')) {
                $payable->onPaymentFailed([
                    'error' => $errorMessage,
                    'transaction_id' => $transactionId,
                    'metadata' => array_merge($metadata, $additionalData)
                ]);
            }
        }

        return response()->json(['message' => 'Failure processed'], 200);
    }

    /**
     * Check if webhook was already processed
     */
    protected function isAlreadyProcessed(string $webhookId): bool
    {
        $cacheKey = "webhook:processed:{$this->getProviderCode()}:{$webhookId}";

        // Check cache first (faster)
        if (Cache::has($cacheKey)) {
            return true;
        }

        // Check database
        $exists = WebhookEvent::where('provider_code', $this->getProviderCode())
            ->where('webhook_id', $webhookId)
            ->exists();

        if ($exists) {
            // Cache for 24 hours to speed up future checks
            Cache::put($cacheKey, true, 86400);
        }

        return $exists;
    }

    /**
     * Record webhook in database
     */
    protected function recordWebhook(string $webhookId, array $payload): void
    {
        $webhookEvent = new WebhookEvent();
        $webhookEvent->provider_code = $this->getProviderCode();
        $webhookEvent->webhook_id = $webhookId;
        $webhookEvent->payload = $payload;
        $webhookEvent->processed_at = now();
        $webhookEvent->save();

        // Cache for 24 hours
        $cacheKey = "webhook:processed:{$this->getProviderCode()}:{$webhookId}";
        Cache::put($cacheKey, true, 86400);
    }

    /**
     * Load payable model
     */
    protected function loadPayable(string $payableType, int $payableId)
    {
        if (!class_exists($payableType)) {
            Log::error('Invalid payable type', ['type' => $payableType]);
            return null;
        }

        return app($payableType)->find($payableId);
    }

    /**
     * Determine if error is non-recoverable
     */
    protected function isNonRecoverableError(\Exception $e): bool
    {
        // Add logic to determine if error should not trigger retry
        return $e instanceof \InvalidArgumentException ||
               $e instanceof \RuntimeException && Str::contains($e->getMessage(), 'not found');
    }

    /**
     * Get provider code
     */
    abstract protected function getProviderCode(): string;

    /**
     * Extract webhook ID from request
     */
    abstract protected function extractWebhookId(Request $request): string;

    /**
     * Verify webhook signature
     */
    abstract protected function verifySignature(Request $request): bool;

    /**
     * Process the webhook event based on type
     */
    abstract protected function processWebhookEvent(Request $request);
}

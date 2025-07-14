<?php

namespace Condoedge\Finance\Billing\Webhooks;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StripeWebhookProcessor extends WebhookProcessor
{
    public function __construct(
        private string $webhookSecret
    ) {}
    
    protected function getProviderCode(): string
    {
        return 'stripe';
    }
    
    protected function extractWebhookId(Request $request): string
    {
        // Stripe sends event ID in the payload
        $payload = json_decode($request->getContent(), true);
        return $payload['id'] ?? '';
    }
    
    protected function verifySignature(Request $request): bool
    {
        $signature = $request->header('Stripe-Signature');
        if (!$signature) {
            Log::warning('Stripe webhook missing signature header');
            return false;
        }
        
        $payload = $request->getContent();
        
        // Parse the signature header
        $elements = explode(',', $signature);
        $signatureData = [];
        
        foreach ($elements as $element) {
            $parts = explode('=', $element, 2);
            if (count($parts) === 2) {
                $signatureData[$parts[0]] = $parts[1];
            }
        }
        
        if (!isset($signatureData['t']) || !isset($signatureData['v1'])) {
            Log::warning('Stripe webhook invalid signature format');
            return false;
        }
        
        // Verify timestamp to prevent replay attacks (5 minute tolerance)
        $timestamp = $signatureData['t'];
        $tolerance = 300; // 5 minutes
        
        if (abs(time() - $timestamp) > $tolerance) {
            Log::warning('Stripe webhook timestamp outside tolerance window');
            return false;
        }
        
        // Compute expected signature
        $signedPayload = $timestamp . '.' . $payload;
        $expectedSignature = hash_hmac('sha256', $signedPayload, $this->webhookSecret);
        
        // Compare signatures
        return hash_equals($expectedSignature, $signatureData['v1']);
    }
    
    protected function processWebhookEvent(Request $request)
    {
        $payload = json_decode($request->getContent(), true);
        $eventType = $payload['type'] ?? '';
        $eventData = $payload['data']['object'] ?? [];
        
        Log::info('Processing Stripe webhook', [
            'event_type' => $eventType,
            'event_id' => $payload['id'] ?? ''
        ]);
        
        switch ($eventType) {
            case 'payment_intent.succeeded':
                return $this->handlePaymentIntentSucceeded($eventData);
                
            case 'payment_intent.payment_failed':
                return $this->handlePaymentIntentFailed($eventData);
                
            case 'charge.succeeded':
                // Alternative event for successful charges
                return $this->handleChargeSucceeded($eventData);
                
            case 'charge.failed':
                // Alternative event for failed charges
                return $this->handleChargeFailed($eventData);
                
            case 'payment_intent.canceled':
                return $this->handlePaymentIntentCanceled($eventData);
                
            default:
                Log::info('Unhandled Stripe webhook event', ['type' => $eventType]);
                return response()->json(['message' => 'Event acknowledged'], 200);
        }
    }
    
    /**
     * Handle successful payment intent
     */
    protected function handlePaymentIntentSucceeded(array $paymentIntent)
    {
        $metadata = $paymentIntent['metadata'] ?? [];
        $transactionId = $paymentIntent['id'];
        $amount = ($paymentIntent['amount'] ?? 0) / 100; // Convert from cents
        
        $this->processPaymentSuccess(
            $transactionId,
            $amount,
            $metadata,
            [
                'stripe_status' => $paymentIntent['status'],
                'stripe_payment_method' => $paymentIntent['payment_method'] ?? null,
                'stripe_currency' => $paymentIntent['currency'] ?? 'usd',
            ]
        );
        
        return response()->json(['message' => 'Success processed'], 200);
    }
    
    /**
     * Handle failed payment intent
     */
    protected function handlePaymentIntentFailed(array $paymentIntent)
    {
        $metadata = $paymentIntent['metadata'] ?? [];
        $transactionId = $paymentIntent['id'];
        $lastError = $paymentIntent['last_payment_error'] ?? [];
        $errorMessage = $lastError['message'] ?? 'Payment failed';
        
        $this->processPaymentFailure(
            $transactionId,
            $errorMessage,
            $metadata,
            [
                'stripe_status' => $paymentIntent['status'],
                'stripe_error_code' => $lastError['code'] ?? null,
                'stripe_error_type' => $lastError['type'] ?? null,
            ]
        );
        
        return response()->json(['message' => 'Failure processed'], 200);
    }
    
    /**
     * Handle successful charge (alternative to payment intent)
     */
    protected function handleChargeSucceeded(array $charge)
    {
        $metadata = $charge['metadata'] ?? [];
        $transactionId = $charge['payment_intent'] ?? $charge['id'];
        $amount = ($charge['amount'] ?? 0) / 100; // Convert from cents
        
        // Only process if not already processed via payment_intent
        if (!$this->isAlreadyProcessed($transactionId)) {
            $this->processPaymentSuccess(
                $transactionId,
                $amount,
                $metadata,
                [
                    'stripe_charge_id' => $charge['id'],
                    'stripe_status' => $charge['status'],
                    'stripe_currency' => $charge['currency'] ?? 'usd',
                ]
            );
        }
        
        return response()->json(['message' => 'Charge processed'], 200);
    }
    
    /**
     * Handle failed charge
     */
    protected function handleChargeFailed(array $charge)
    {
        $metadata = $charge['metadata'] ?? [];
        $transactionId = $charge['payment_intent'] ?? $charge['id'];
        $errorMessage = $charge['failure_message'] ?? 'Charge failed';
        
        $this->processPaymentFailure(
            $transactionId,
            $errorMessage,
            $metadata,
            [
                'stripe_charge_id' => $charge['id'],
                'stripe_failure_code' => $charge['failure_code'] ?? null,
            ]
        );
        
        return response()->json(['message' => 'Failure processed'], 200);
    }
    
    /**
     * Handle canceled payment intent
     */
    protected function handlePaymentIntentCanceled(array $paymentIntent)
    {
        $metadata = $paymentIntent['metadata'] ?? [];
        $transactionId = $paymentIntent['id'];
        
        $this->processPaymentFailure(
            $transactionId,
            __('translate.payment-canceled'),
            $metadata,
            [
                'stripe_status' => 'canceled',
                'stripe_cancellation_reason' => $paymentIntent['cancellation_reason'] ?? null,
            ]
        );
        
        return response()->json(['message' => 'Cancellation processed'], 200);
    }
}

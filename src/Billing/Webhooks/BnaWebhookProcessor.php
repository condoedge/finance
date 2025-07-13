<?php

namespace Condoedge\Finance\Billing\Webhooks;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BnaWebhookProcessor extends WebhookProcessor
{
    public function __construct(
        private string $secretKey
    ) {}
    
    protected function getProviderCode(): string
    {
        return 'bna';
    }
    
    protected function extractWebhookId(Request $request): string
    {
        // BNA uses referenceUUID as unique identifier
        return $request->input('referenceUUID', '');
    }
    
    protected function verifySignature(Request $request): bool
    {
        // For now skipping signature verification
        return true;
        $signature = $request->header('X-BNA-Signature');
        if (!$signature) {
            Log::warning('BNA webhook missing signature header');
            return false;
        }
        
        $payload = $request->getContent();
        $expectedSignature = hash_hmac('sha256', $payload, $this->secretKey);
        
        return hash_equals($expectedSignature, $signature);
    }
    
    protected function processWebhookEvent(Request $request)
    {
        $status = $request->input('status');
        $transactionId = $request->input('referenceUUID');
        $amount = $request->input('amount', 0);
        $metadata = $request->input('metadata', []);
        
        Log::info('Processing BNA webhook', [
            'status' => $status,
            'transaction_id' => $transactionId,
            'amount' => $amount
        ]);
        
        switch ($status) {
            case 'APPROVED':
                $this->processPaymentSuccess(
                    $transactionId,
                    $amount,
                    $metadata,
                    [
                        'bna_status' => $status,
                        'bna_response' => $request->only(['customerEmail', 'customerName'])
                    ]
                );
                
                return response()->json(['message' => 'Success processed'], 200);
                
            case 'DECLINED':
            case 'ERROR':
                $errorMessage = $request->input('errorMessage', 'Payment failed');
                $this->processPaymentFailure(
                    $transactionId,
                    $errorMessage,
                    $metadata,
                    [
                        'bna_status' => $status,
                        'bna_error_code' => $request->input('errorCode')
                    ]
                );
                
                return response()->json(['message' => 'Failure processed'], 200);
                
            case 'PENDING':
                // Just acknowledge pending status
                Log::info('BNA payment pending', ['transaction_id' => $transactionId]);
                return response()->json(['message' => 'Pending acknowledged'], 200);
                
            default:
                Log::warning('Unknown BNA webhook status', [
                    'status' => $status,
                    'transaction_id' => $transactionId
                ]);
                return response()->json(['message' => 'Unknown status'], 200);
        }
    }
}

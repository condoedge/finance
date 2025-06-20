<?php

namespace Condoedge\Finance\Billing;

use Condoedge\Finance\Models\Invoice;
use Condoedge\Finance\Models\PaymentTypeEnum;
use Illuminate\Support\Facades\Log;

class PaymentGatewayResolver
{
    protected static $invoiceContext;

    /**
     * Legacy method - maintains backward compatibility
     * 
     * @deprecated Use resolveForInvoice() instead for stateless approach
     */
    public static function setContext(Invoice $invoiceContext)
    {
        self::$invoiceContext = $invoiceContext;
    }

    /**
     * Legacy resolver - maintains backward compatibility
     * 
     * @deprecated Use resolveForInvoice() instead for stateless approach
     */
    public static function resolve(): PaymentGatewayInterface
    {
        if (self::$invoiceContext === null) {
            Log::critical('PaymentGatewayResolver: Context is not set. Please set the context before resolving.');
            
            throw new \RuntimeException('Payment gateway context is not set.');
        }

        return self::resolveForInvoice(self::$invoiceContext);
    }

    /**
     * Stateless resolver - recommended approach
     * 
     * Resolves payment gateway for specific invoice without using static state
     */
    public static function resolveForInvoice(Invoice $invoice): PaymentGatewayInterface
    {
        return self::resolveForPaymentType($invoice->payment_type_id);
    }

    /**
     * Stateless resolver by payment type
     * 
     * Resolves payment gateway for specific payment type
     */
    public static function resolveForPaymentType(PaymentTypeEnum $paymentType): PaymentGatewayInterface
    {
        $gatewayClass = $paymentType->getPaymentGateway();
        
        if (!class_exists($gatewayClass)) {
            throw new \RuntimeException("Payment gateway class does not exist: {$gatewayClass}");
        }

        return new $gatewayClass();
    }

    /**
     * Stateless resolver with custom context
     * 
     * Allows resolving with custom parameters for advanced use cases
     */
    public static function resolveWithContext(PaymentTypeEnum $paymentType, array $context = []): PaymentGatewayInterface
    {
        $gateway = self::resolveForPaymentType($paymentType);
        
        // If gateway supports context initialization
        if (method_exists($gateway, 'initializeContext')) {
            $gateway->initializeContext($context);
        }
        
        return $gateway;
    }

    /**
     * Get all available payment gateways
     * 
     * Returns array of payment types and their corresponding gateways
     */
    public static function getAvailableGateways(): array
    {
        $gateways = [];
        
        foreach (PaymentTypeEnum::cases() as $paymentType) {
            $gateways[$paymentType->value] = [
                'payment_type' => $paymentType,
                'gateway_class' => $paymentType->getPaymentGateway(),
                'label' => $paymentType->label(),
            ];
        }
        
        return $gateways;
    }

    /**
     * Clear static context (useful for testing)
     */
    public static function clearContext(): void
    {
        self::$invoiceContext = null;
    }
}

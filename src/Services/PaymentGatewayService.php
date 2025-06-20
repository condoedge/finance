<?php

namespace Condoedge\Finance\Services;

use Condoedge\Finance\Billing\PaymentGatewayInterface;
use Condoedge\Finance\Billing\PaymentGatewayResolver;
use Condoedge\Finance\Models\Invoice;
use Condoedge\Finance\Models\PaymentTypeEnum;
use Condoedge\Finance\Models\GlAccount;

/**
 * Stateless Payment Gateway Service
 * 
 * Provides a clean, stateless API for payment gateway operations
 * without relying on static context.
 */
class PaymentGatewayService
{
    /**
     * Get cash account for a specific invoice
     */
    public function getCashAccountForInvoice(Invoice $invoice): GlAccount
    {
        $gateway = PaymentGatewayResolver::resolveForInvoice($invoice);
        return $gateway->getCashAccount();
    }

    /**
     * Get cash account for a specific payment type
     */
    public function getCashAccountForPaymentType(PaymentTypeEnum $paymentType): GlAccount
    {
        $gateway = PaymentGatewayResolver::resolveForPaymentType($paymentType);
        return $gateway->getCashAccount();
    }

    /**
     * Get payment gateway for invoice
     */
    public function getGatewayForInvoice(Invoice $invoice): PaymentGatewayInterface
    {
        return PaymentGatewayResolver::resolveForInvoice($invoice);
    }

    /**
     * Get payment gateway for payment type
     */
    public function getGatewayForPaymentType(PaymentTypeEnum $paymentType): PaymentGatewayInterface
    {
        return PaymentGatewayResolver::resolveForPaymentType($paymentType);
    }

    /**
     * Get payment gateway with custom context
     */
    public function getGatewayWithContext(PaymentTypeEnum $paymentType, array $context = []): PaymentGatewayInterface
    {
        return PaymentGatewayResolver::resolveWithContext($paymentType, $context);
    }

    /**
     * Get all available payment gateways
     */
    public function getAvailableGateways(): array
    {
        return PaymentGatewayResolver::getAvailableGateways();
    }

    /**
     * Process refund for invoice
     */
    public function processRefund(Invoice $invoice): void
    {
        $gateway = $this->getGatewayForInvoice($invoice);
        $gateway->refundOrder();
    }

    /**
     * Setup routes for all gateways
     */
    public function setupAllRoutes(): void
    {
        $gateways = $this->getAvailableGateways();
        
        foreach ($gateways as $gatewayInfo) {
            $gateway = $this->getGatewayForPaymentType($gatewayInfo['payment_type']);
            $gateway->setRoutes();
        }
    }

    /**
     * Validate payment type has working gateway
     */
    public function validatePaymentType(PaymentTypeEnum $paymentType): bool
    {
        try {
            $gateway = $this->getGatewayForPaymentType($paymentType);
            $account = $gateway->getCashAccount();
            return $account !== null;
        } catch (\Exception $e) {
            return false;
        }
    }
}

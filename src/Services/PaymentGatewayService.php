<?php

namespace Condoedge\Finance\Services;

use Condoedge\Finance\Billing\PaymentGatewayInterface;
use Condoedge\Finance\Billing\PaymentGatewayResolver;
use Condoedge\Finance\Models\Invoice;
use Condoedge\Finance\Models\PaymentMethodEnum;

/**
 * Stateless Payment Gateway Service
 *
 * Provides a clean, stateless API for payment gateway operations
 * without relying on static context.
 */
class PaymentGatewayService
{
    /**
     * Get payment gateway for invoice
     */
    public function getGatewayForInvoice(Invoice $invoice, array $context = []): PaymentGatewayInterface
    {
        return PaymentGatewayResolver::resolveForInvoice($invoice, $context);
    }

    /**
     * Get payment gateway for payment type
     */
    public function getGatewayForPaymentType(PaymentMethodEnum $paymentType): PaymentGatewayInterface
    {
        return PaymentGatewayResolver::resolveForPaymentType($paymentType);
    }

    /**
     * Get payment gateway with custom context
     */
    public function getGatewayWithContext(PaymentMethodEnum $paymentType, array $context = []): PaymentGatewayInterface
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
}

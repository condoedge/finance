<?php

namespace Condoedge\Finance\Facades;

use Condoedge\Finance\Billing\PaymentGatewayInterface;
use Condoedge\Finance\Billing\PaymentGatewayResolver;
use Condoedge\Finance\Models\Invoice;
use Condoedge\Finance\Models\PaymentMethodEnum;
use Condoedge\Finance\Services\PaymentGatewayService;

/**
 * Payment Gateway Facade
 *
 * Supports both legacy (stateful) and new (stateless) approaches
 *
 * Legacy usage (still supported):
 *
 * @method static \Condoedge\Finance\Models\GlAccount getCashAccount()
 * @method static mixed setRoutes()
 *
 * New stateless usage (recommended):
 * @method static \Condoedge\Finance\Models\GlAccount getCashAccountForInvoice(\Condoedge\Finance\Models\Invoice $invoice)
 * @method static \Condoedge\Finance\Models\GlAccount getCashAccountForPaymentType(\Condoedge\Finance\Models\PaymentMethodEnum $paymentType)
 * @method static \Condoedge\Finance\Billing\PaymentGatewayInterface getGatewayForInvoice(\Condoedge\Finance\Models\Invoice $invoice)
 * @method static \Condoedge\Finance\Billing\PaymentGatewayInterface getGatewayForPaymentType(\Condoedge\Finance\Models\PaymentMethodEnum $paymentType)
 * @method static array getAvailableGateways()
 * @method static void processRefund(\Condoedge\Finance\Models\Invoice $invoice)
 * @method static bool validatePaymentType(\Condoedge\Finance\Models\PaymentMethodEnum $paymentType)
 *
 * @mixin \Condoedge\Finance\Billing\PaymentGatewayInterface
 * @mixin \Condoedge\Finance\Services\PaymentGatewayService
 */
class PaymentGateway extends \Illuminate\Support\Facades\Facade
{
    protected static $paymentGatewayService;

    protected static function getFacadeAccessor()
    {
        return PaymentGatewayInterface::class;
    }

    /**
     * Get PaymentGatewayService instance
     */
    protected static function getPaymentGatewayService(): PaymentGatewayService
    {
        if (!self::$paymentGatewayService) {
            self::$paymentGatewayService = app(PaymentGatewayService::class);
        }

        return self::$paymentGatewayService;
    }

    /**
     * Stateless method: Get gateway for invoice
     */
    public static function getGatewayForInvoice(Invoice $invoice, array $context = [])
    {
        return self::getPaymentGatewayService()->getGatewayForInvoice($invoice, $context);
    }

    /**
     * Stateless method: Get gateway for payment type
     */
    public static function getGatewayForPaymentType(PaymentMethodEnum $paymentType)
    {
        return self::getPaymentGatewayService()->getGatewayForPaymentType($paymentType);
    }

    /**
     * Stateless method: Get gateway with context
     */
    public static function getGatewayWithContext(PaymentMethodEnum $paymentType, array $context = [])
    {
        return self::getPaymentGatewayService()->getGatewayWithContext($paymentType, $context);
    }

    /**
     * Get all available gateways
     */
    public static function getAvailableGateways()
    {
        return self::getPaymentGatewayService()->getAvailableGateways();
    }

    /**
     * Process refund for invoice
     */
    public static function processRefund(Invoice $invoice)
    {
        return self::getPaymentGatewayService()->processRefund($invoice);
    }

    /**
     * Validate payment type
     */
    public static function validatePaymentType(PaymentMethodEnum $paymentType)
    {
        return self::getPaymentGatewayService()->validatePaymentType($paymentType);
    }

    /**
     * Setup routes for all gateways
     */
    public static function setupAllRoutes()
    {
        return self::getPaymentGatewayService()->setupAllRoutes();
    }

    /**
     * Clear static context (useful for testing)
     */
    public static function clearContext()
    {
        PaymentGatewayResolver::clearContext();
    }
}

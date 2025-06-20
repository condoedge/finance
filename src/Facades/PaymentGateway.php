<?php

namespace Condoedge\Finance\Facades;

use Condoedge\Finance\Billing\PaymentGatewayInterface;
use Condoedge\Finance\Billing\PaymentGatewayResolver;
use Condoedge\Finance\Services\PaymentGatewayService;
use Condoedge\Finance\Models\Invoice;
use Condoedge\Finance\Models\PaymentTypeEnum;

/**
 * Payment Gateway Facade
 * 
 * Supports both legacy (stateful) and new (stateless) approaches
 * 
 * Legacy usage (still supported):
 * @method static \Condoedge\Finance\Models\GlAccount getCashAccount()
 * @method static mixed setRoutes()
 * 
 * New stateless usage (recommended):
 * @method static \Condoedge\Finance\Models\GlAccount getCashAccountForInvoice(\Condoedge\Finance\Models\Invoice $invoice)
 * @method static \Condoedge\Finance\Models\GlAccount getCashAccountForPaymentType(\Condoedge\Finance\Models\PaymentTypeEnum $paymentType)
 * @method static \Condoedge\Finance\Billing\PaymentGatewayInterface getGatewayForInvoice(\Condoedge\Finance\Models\Invoice $invoice)
 * @method static \Condoedge\Finance\Billing\PaymentGatewayInterface getGatewayForPaymentType(\Condoedge\Finance\Models\PaymentTypeEnum $paymentType)
 * @method static array getAvailableGateways()
 * @method static void processRefund(\Condoedge\Finance\Models\Invoice $invoice)
 * @method static bool validatePaymentType(\Condoedge\Finance\Models\PaymentTypeEnum $paymentType)
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
     * Stateless method: Get cash account for specific invoice
     */
    public static function getCashAccountForInvoice(Invoice $invoice)
    {
        return self::getPaymentGatewayService()->getCashAccountForInvoice($invoice);
    }

    /**
     * Stateless method: Get cash account for specific payment type
     */
    public static function getCashAccountForPaymentType(PaymentTypeEnum $paymentType)
    {
        return self::getPaymentGatewayService()->getCashAccountForPaymentType($paymentType);
    }

    /**
     * Stateless method: Get gateway for invoice
     */
    public static function getGatewayForInvoice(Invoice $invoice)
    {
        return self::getPaymentGatewayService()->getGatewayForInvoice($invoice);
    }

    /**
     * Stateless method: Get gateway for payment type
     */
    public static function getGatewayForPaymentType(PaymentTypeEnum $paymentType)
    {
        return self::getPaymentGatewayService()->getGatewayForPaymentType($paymentType);
    }

    /**
     * Stateless method: Get gateway with context
     */
    public static function getGatewayWithContext(PaymentTypeEnum $paymentType, array $context = [])
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
    public static function validatePaymentType(PaymentTypeEnum $paymentType)
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

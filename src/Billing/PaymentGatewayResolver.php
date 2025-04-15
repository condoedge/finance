<?php

namespace Condoedge\Finance\Billing;

use Condoedge\Finance\Models\Invoice;

class PaymentGatewayResolver
{
    protected static $invoiceContext;

    public static function setContext(Invoice $invoiceContext)
    {
        self::$invoiceContext = $invoiceContext;
    }

    public static function resolve(): PaymentGatewayInterface
    {
        if (self::$invoiceContext === null) {
            throw new \RuntimeException('Payment gateway context is not set.');
        }

        return new (self::$invoiceContext->payment_type_id->getPaymentGateway());
    }
}
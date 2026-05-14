<?php

namespace Condoedge\Finance\Billing\Contracts;

use Condoedge\Finance\Billing\Core\PaymentContext;
use Condoedge\Finance\Billing\Core\PaymentResult;

interface PaymentProcessorInterface
{
    public function managePaymentResult(PaymentResult $result, PaymentContext $context);

    public function processPayment(PaymentContext $context);
}

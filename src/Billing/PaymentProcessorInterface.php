<?php

namespace Condoedge\Finance\Billing;

use Kompo\Elements\BaseElement;

interface PaymentProcessorInterface
{
    public function managePaymentResult(PaymentResult $result, PaymentContext $context);
    
    public function processPayment(PaymentContext $context);

    public function getPaymentForm(PaymentContext $context): ?BaseElement;
}
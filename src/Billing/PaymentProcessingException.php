<?php

namespace Condoedge\Finance\Billing;

use Condoedge\Finance\Billing\PaymentContext;
use Condoedge\Finance\Billing\PaymentResult;
use Exception;

class PaymentProcessingException extends Exception {
    public function __construct(
        public readonly PaymentContext $context,
        public readonly ?PaymentResult $result,
        string $message,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }
}
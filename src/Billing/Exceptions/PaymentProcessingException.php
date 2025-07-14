<?php

namespace Condoedge\Finance\Billing\Exceptions;

use Condoedge\Finance\Billing\Core\PaymentContext;
use Condoedge\Finance\Billing\Core\PaymentResult;
use Exception;
use Illuminate\Validation\ValidationException;

class PaymentProcessingException extends Exception {
    public function __construct(
        public readonly PaymentContext $context,
        public readonly ?PaymentResult $result,
        string $message,
        ?\Throwable $previous = null
    ) {
        if ($previous instanceof ValidationException) {
            throw $previous; // Re-throw the validation exception to be handled by the caller
        }
        
        parent::__construct($message, 0, $previous);
    }
}
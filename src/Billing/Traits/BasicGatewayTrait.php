<?php

namespace Condoedge\Finance\Billing\Traits;

use Condoedge\Finance\Billing\Core\ErrorClassification;
use Condoedge\Finance\Billing\Core\PaymentFlowEnum;

/**
 * Default implementations of the new dynamic-provider methods on
 * PaymentGatewayInterface. Existing providers (Stripe, BNA) use this trait
 * to get sane defaults without churn:
 *  - flow = INLINE (they render Kompo forms)
 *  - error classification defaults to UNKNOWN (each provider should override
 *    classifyError() with provider-specific logic — see StripePaymentProvider).
 */
trait BasicGatewayTrait
{
    public function getCheckoutFlow(): PaymentFlowEnum
    {
        return PaymentFlowEnum::INLINE;
    }

    public function classifyError(\Throwable $e): ErrorClassification
    {
        return ErrorClassification::unknown($e->getMessage());
    }

    public function getDisplayName(): string
    {
        return ucfirst($this->getCode());
    }
}

<?php

namespace Condoedge\Finance\Billing\Core;

use Condoedge\Finance\Billing\Contracts\PayableInterface;
use Condoedge\Finance\Models\PaymentMethodEnum;

class PaymentContext
{
    public function __construct(
        public readonly PayableInterface $payable,
        public readonly PaymentMethodEnum $paymentMethod,
        public readonly array $paymentData = [], // Card details, etc.
        public readonly ?string $returnUrl = null,
        public readonly ?string $cancelUrl = null,
        public readonly array $metadata = [],
    ) {
    }

    public function toProviderMetadata(): array
    {
        return array_merge(
            [
                'payable_type' => $this->payable->getPayableType(),
                'payable_id' => $this->payable->getPayableId(),
                'team_id' => $this->payable->getTeamId(),
                'payment_method_id' => $this->paymentMethod->value,
            ],
            $this->payable->getPaymentMetadata(),
            $this->metadata
        );
    }
}

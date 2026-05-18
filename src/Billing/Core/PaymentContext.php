<?php

namespace Condoedge\Finance\Billing\Core;

use Condoedge\Finance\Billing\Contracts\PayableInterface;
use Condoedge\Finance\Models\PaymentMethodEnum;

class PaymentContext
{
    /**
     * @var int|null Cached team_id resolved from payable; computed lazily.
     */
    private ?int $resolvedTeamId = null;

    public function __construct(
        public readonly PayableInterface $payable,
        public readonly PaymentMethodEnum $paymentMethod,
        public readonly array $paymentData = [], // Card details, etc.
        public readonly ?string $returnUrl = null,
        public readonly ?string $cancelUrl = null,
        public readonly array $metadata = [],
        /**
         * Optional override. Normally resolved from $payable->getTeamId().
         * Set this when a caller (e.g. webhook reconstruction) doesn't have a
         * payable but knows the team. Reading via getTeamId() handles both.
         */
        public readonly ?int $teamIdOverride = null,
    ) {
    }

    /**
     * Team ID for provider/credential lookup. Required for the resolver to
     * find team_payment_providers rows. Falls back to the current user's
     * team if both payable and override are null (legacy code path).
     */
    public function getTeamId(): ?int
    {
        if ($this->resolvedTeamId !== null) {
            return $this->resolvedTeamId;
        }

        if ($this->teamIdOverride !== null) {
            return $this->resolvedTeamId = $this->teamIdOverride;
        }

        $payableTeam = $this->payable->getTeamId();
        if ($payableTeam) {
            return $this->resolvedTeamId = $payableTeam;
        }

        return $this->resolvedTeamId = auth()->user()?->current_team_id;
    }

    public function toProviderMetadata(): array
    {
        return array_merge(
            [
                'payable_type' => $this->payable->getPayableType(),
                'payable_id' => $this->payable->getPayableId(),
                'team_id' => $this->getTeamId(),
                'payment_method_id' => $this->paymentMethod->value,
            ],
            $this->payable->getPaymentMetadata(),
            $this->metadata
        );
    }
}

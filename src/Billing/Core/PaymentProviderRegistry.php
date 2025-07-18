<?php

namespace Condoedge\Finance\Billing\Core;

use Condoedge\Finance\Billing\Contracts\PaymentGatewayInterface;

class PaymentProviderRegistry
{
    /**
     * Registered payment providers
     *
     * @var array<PaymentGatewayInterface>
     */
    private array $providers = [];

    public function __construct()
    {
        // Allow custom providers via service provider
        foreach (config('kompo-finance.payment_providers', []) as $providerClass) {
            $this->register(app($providerClass));
        }
    }

    public function register(PaymentGatewayInterface $provider): void
    {
        $this->providers[$provider->getCode()] = $provider;
    }

    public function get(string $code): PaymentGatewayInterface
    {
        if (!isset($this->providers[$code])) {
            throw new \Exception("Payment provider '{$code}' not found");
        }

        return $this->providers[$code];
    }

    /**
     * @return array<PaymentGatewayInterface>
     */
    public function all(): array
    {
        return $this->providers;
    }
}

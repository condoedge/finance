<?php

namespace Condoedge\Finance\Billing\Core;

use Condoedge\Finance\Billing\Contracts\PaymentGatewayInterface;

class PaymentProviderRegistry
{
    /**
     * @var array<string, PaymentGatewayInterface>
     */
    private array $providers = [];

    public function __construct()
    {
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
            throw new \RuntimeException("Payment provider '{$code}' not registered");
        }

        return $this->providers[$code];
    }

    public function has(string $code): bool
    {
        return isset($this->providers[$code]);
    }

    /**
     * @return array<string, PaymentGatewayInterface>
     */
    public function all(): array
    {
        return $this->providers;
    }
}

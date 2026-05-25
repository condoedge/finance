<?php

namespace Condoedge\Finance\Billing\Contracts;

use Condoedge\Finance\Billing\Core\PaymentContext;
use Condoedge\Finance\Billing\Core\PaymentResult;

/**
 * Opt-in: a provider that can run a real sandbox transaction with a
 * deterministic outcome (e.g. Stripe via test PaymentMethods). Providers that
 * cannot (hosted redirects, no sandbox) simply do not implement this — the
 * payment screen then simulates the outcome directly.
 */
interface SimulatesPaymentInSandbox
{
    public function simulateSandboxPayment(PaymentContext $context, bool $shouldSucceed): PaymentResult;
}

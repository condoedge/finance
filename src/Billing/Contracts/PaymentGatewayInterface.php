<?php

namespace Condoedge\Finance\Billing\Contracts;

use Condoedge\Finance\Billing\Core\ErrorClassification;
use Condoedge\Finance\Billing\Core\PaymentContext;
use Condoedge\Finance\Billing\Core\PaymentFlowEnum;
use Condoedge\Finance\Billing\Core\PaymentResult;
use Illuminate\Routing\Router;
use Kompo\Elements\BaseElement;

interface PaymentGatewayInterface
{
    /**
     * Provider identifier (slug). Stored in fin_team_payment_providers.provider_code
     * and fin_payment_traces.payment_provider_code.
     */
    public function getCode(): string;

    /**
     * Human-readable name for UI ("Stripe", "Moneris", "BNA Smart Payment").
     */
    public function getDisplayName(): string;

    /**
     * Execute the payment. For HOSTED_REDIRECT flows this is called twice:
     *  1. Without a provider ticket in $context->paymentData — provider creates
     *     a checkout session and returns PaymentResult::pending() with action=REDIRECT.
     *  2. With the ticket present (after the user returns) — provider looks up
     *     the receipt and returns success/failure.
     */
    public function processPayment(PaymentContext $context): PaymentResult;

    /**
     * INLINE flow only: return the Kompo form that collects payment details.
     * HOSTED_REDIRECT providers return null — the "Pay" button submits straight
     * to processPayment() which initiates the redirect.
     */
    public function getPaymentForm(PaymentContext $context): ?BaseElement;

    /**
     * Describes how this provider collects payment details. Drives whether
     * InvoicePayModal renders an inline form (INLINE) or just a "Pay with X"
     * button that triggers redirect-out (HOSTED_REDIRECT).
     */
    public function getCheckoutFlow(): PaymentFlowEnum;

    /**
     * Classify a thrown error for fallback / health-check decisions.
     * PERMANENT (card declined) suppresses fallback; everything else triggers
     * the next provider in the chain.
     */
    public function classifyError(\Throwable $e): ErrorClassification;

    /**
     * Payment methods this provider can handle. The resolver intersects this
     * with the team's configured providers (fin_team_payment_providers) to
     * decide eligibility — capability vs enabled-by-admin is separate.
     */
    public function getSupportedPaymentMethods(): array;

    /**
     * Register webhook routes.
     */
    public function registerWebhookRoutes(Router $router): void;
}

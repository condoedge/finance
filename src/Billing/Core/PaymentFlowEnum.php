<?php

namespace Condoedge\Finance\Billing\Core;

/**
 * Describes how a payment provider collects card details.
 *
 * INLINE: Provider exposes a Kompo form (Stripe Elements, BNA card form).
 *         InvoicePayModal renders that form before submit.
 *
 * HOSTED_REDIRECT: Provider's checkout is a separate page. InvoicePayModal
 *         renders only a "Pay with X" button — clicking it submits to
 *         processPayment() which calls the provider's preload, then redirects
 *         the browser to the provider's hosted page. The provider sends the
 *         user back to MonerisReturnController (or equivalent) afterwards,
 *         which re-enters processPayment() with the ticket to confirm.
 */
enum PaymentFlowEnum: string
{
    case INLINE = 'inline';
    case HOSTED_REDIRECT = 'hosted_redirect';

    public function isHosted(): bool
    {
        return $this === self::HOSTED_REDIRECT;
    }
}

<?php

namespace Condoedge\Finance\Models;

interface InvoiceableInterface
{
    /**
     * Called when the invoice is fully paid.
     */
    public function onCompletePayment(): void;

    /**
     * Called when the invoice is partially paid.
     */
    public function onPartialPayment(): void;

    public function onConsideredAsInitialPaid(): void;

    public function onOverdue(): void;

    public function getDisplayForInvoiceAttribute(): string;
}

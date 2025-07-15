<?php

namespace Condoedge\Finance\Models;

use Carbon\Carbon;
use Kompo\Elements\BaseElement;

/**
 * PaymentTerm Model
 * This model represents a payment term in the finance system.
 * It includes properties for the term type, name, description, and settings.
 *
 * @property int $id Unique identifier for the payment term
 * @property string $term_name Name of the payment term
 * @property string|null $term_description Description of the payment term
 * @property PaymentTermTypeEnum $term_type Type of payment term (e.g., Installment, COD)
 * @property array|null $settings Additional settings for the payment term, such as installment periods or net terms
 */
class PaymentTerm extends AbstractMainFinanceModel
{
    protected $table = 'fin_payment_terms';

    protected $casts = [
        'settings' => 'array',
        'term_type' => PaymentTermTypeEnum::class,
    ];

    public function getDisplayAttribute(): string
    {
        return $this->term_name;
    }

    public function preview(Invoice $invoice): BaseElement
    {
        return $this->term_type->preview($invoice, $this->settings);
    }

    // SCOPES
    public function scopeCod($query)
    {
        return $query->where('term_type', PaymentTermTypeEnum::COD);
    }

    // ACTIONS
    public function calculateDueDate(string|Carbon $invoiceDate): \DateTime
    {
        $settings = $this->settings ?? [];

        return $this->term_type->calculateDueDate(carbon($invoiceDate), $settings);
    }

    public function deletable()
    {
        return true;
    }

    public function consideredAsInitialPaid(Invoice $invoice): bool
    {
        return $this->term_type->consideredAsInitialPaid($invoice);
    }
}

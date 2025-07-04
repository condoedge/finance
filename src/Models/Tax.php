<?php

namespace Condoedge\Finance\Models;

use Condoedge\Finance\Casts\SafeDecimalCast;

/**
 * Class InvoiceDetailTax
 *
 * @package Condoedge\Finance\Models
 *
 * @TRIGGERED BY: tr_invoice_details_after_insert (insert_invoice_taxes_v0001.sql)
 *
 * @property int $id
 * @property string $name
 * @property int $invoice_detail_id Foreign key to fin_invoices
 * @property int $account_id Foreign key to fin_gl_accounts
 * @property int $tax_id Foreign key to the original tax fin_taxes. The tax rate can mismatch if it was changed
 * @property \Condoedge\Finance\Casts\SafeDecimal $tax_amount Tax amount
 * @property \Condoedge\Finance\Casts\SafeDecimal $rate Tax rate as percentage / 100
 * @property-read \Condoedge\Finance\Models\Invoice $invoice
 */
class Tax extends AbstractMainFinanceModel
{
    protected $table = 'fin_taxes';

    protected $casts = [
        'rate' => SafeDecimalCast::class,
    ];

    /* RELATIONSHIPS */
    public function groups()
    {
        return $this->belongsToMany(TaxGroup::class, 'fin_taxes_group_taxes', 'tax_id', 'tax_group_id');
    }

    /* ATTRIBUTES */
    public function getCompleteLabelAttribute()
    {
        return $this->name . ' (' . $this->rate->multiply(100) . '%)';
    }

    public function getCompleteLabelHtmlAttribute()
    {
        return '<span data-name="'.$this->name.'" data-tax="'.$this->rate.'" data-id="'.$this->id.'">'.$this->complete_label.'</span>';
    }

    /* CALCULATED FIELDS */

    /* SCOPES */
    public function scopeActive($query)
    {
        return $query->where('valide_from', '<=', now())
            ->where(fn ($q) => $q->where('valide_to', '>=', now())->orWhereNull('valide_to'));
    }

    /* ACTIONS */

    /* ELEMENTS */
}

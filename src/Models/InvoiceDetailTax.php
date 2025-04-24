<?php

namespace Condoedge\Finance\Models;

use Condoedge\Finance\Events\InvoiceDetailGenerated;
use Illuminate\Support\Facades\DB;

/**
 * Class InvoiceDetailTax
 * 
 * @package Condoedge\Finance\Models
 * 
 * @TRIGGERED BY: tr_invoice_details_after_insert (insert_invoice_taxes_v0001.sql)
 * 
 * @property int $id
 * @property int $invoice_detail_id Foreign key to fin_invoices
 * @property int $account_id Foreign key to fin_accounts
 * @property int|null $tax_amount Tax amount 
 * @property float $tax_rate Tax rate as percentage / 100
 * 
 * @property-read \Condoedge\Finance\Models\Invoice $invoice
 */
class InvoiceDetailTax extends AbstractMainFinanceModel
{
    protected $table = 'fin_invoice_detail_taxes';

    /* RELATIONSHIPS */
    public function invoiceDetail()
    {
        return $this->belongsTo(InvoiceDetail::class, 'invoice_detail_id');
    }

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    /* ATTRIBUTES */

    /* CALCULATED FIELDS */

    /* SCOPES */

    /* ACTIONS */

    /* INTEGRITY */
    public static function checkIntegrity($ids = null): void
    {
        DB::table('fin_invoice_detail_taxes')
            ->when($ids, function ($query) use ($ids) {
                $query->whereIn('id', $ids);
            })->update([
                'tax_amount' => DB::raw('get_updated_tax_amount_for_taxes(fin_invoice_detail_taxes.invoice_detail_id, fin_invoice_detail_taxes.tax_rate)'),
            ]);
    }

    /* ELEMENTS */    
}
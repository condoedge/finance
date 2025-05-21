<?php

namespace Condoedge\Finance\Models;

use Condoedge\Finance\Casts\SafeDecimalCast;
use Condoedge\Finance\Facades\InvoiceDetailModel;
use Condoedge\Finance\Facades\TaxModel;
use Condoedge\Finance\Models\Dto\Taxes\UpsertManyTaxDetailDto;
use Condoedge\Finance\Models\Dto\Taxes\UpsertTaxDetailDto;
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
 * @property int $tax_id Foreign key to the original tax fin_taxes. The tax rate can mismatch if it was changed
 * @property int|null $tax_amount Tax amount 
 * @property \Condoedge\Finance\Casts\SafeDecimal $tax_rate Tax rate as percentage / 100
 * 
 * @property-read \Condoedge\Finance\Models\Invoice $invoice
 */
class InvoiceDetailTax extends AbstractMainFinanceModel
{
    protected $table = 'fin_invoice_detail_taxes';

    protected $casts = [
        'tax_rate' => SafeDecimalCast::class,
        'tax_amount' => SafeDecimalCast::class,
    ];

    /* RELATIONSHIPS */
    public function invoiceDetail()
    {
        return $this->belongsTo(InvoiceDetail::class, 'invoice_detail_id');
    }

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function tax()
    {
        return $this->belongsTo(Tax::class, 'tax_id');
    }

    /* ATTRIBUTES */

    /* CALCULATED FIELDS */
    public function getCompleteLabelAttribute()
    {
        return $this->tax->name . ' (' . $this->tax_rate * 100 . '%)';
    }

    public function getCompleteLabelHtmlAttribute()
    {
        return '<span data-name="' . $this->tax->name . '" data-tax="' . $this->tax_rate . '" data-id="' . $this->tax_id . '">' . $this->complete_label . '</span>';
    }

    /* SCOPES */

    /* ACTIONS */
    public static function upsertForInvoiceDetailFromTax(UpsertTaxDetailDto $data)
    {
        if ($invoiceDetailTax = static::where('invoice_detail_id', $data->invoice_detail_id)->where('tax_id', $data->tax_id)->first()) {
            return $invoiceDetailTax;
        }

        $tax = TaxModel::findOrFail($data->tax_id);
        $invoiceDetail = InvoiceDetailModel::findOrFail($data->invoice_detail_id);

        $invoiceDetailTax = new self();
        $invoiceDetailTax->invoice_detail_id = $data->invoice_detail_id;
        $invoiceDetailTax->tax_id = $tax->id;
        $invoiceDetailTax->tax_rate = $tax->rate;
        // It will be recaculated so it doesn't matter
        $invoiceDetailTax->tax_amount = $invoiceDetail->extended_price->multiply($tax->rate);
        $invoiceDetailTax->save();

        return $invoiceDetailTax;
    }

    public static function upsertManyForInvoiceDetail(UpsertManyTaxDetailDto $data)
    {

        foreach (($data->taxes_ids ?? []) as $taxId) {
            InvoiceDetailTax::upsertForInvoiceDetailFromTax(new UpsertTaxDetailDto([
                'invoice_detail_id' => $data->invoice_detail_id,
                'tax_id' => $taxId,
            ]));
        }

        $invoiceDetail = InvoiceDetailModel::findOrFail($data->invoice_detail_id);

        $invoiceDetail->invoiceTaxes()->whereNotIn('tax_id', $data->taxes_ids ?? [])->get()->each->delete();
    }

    public static function getAllForInvoiceDetail(int $invoiceDetailId, ?string $taxName = null)
    {
        return static::where('invoice_detail_id', $invoiceDetailId)
            ->when($taxName, fn($q) => $q->whereHas('tax', function ($query) use ($taxName) {
                $query->where('name', $taxName);
            })->withAggregate('tax', 'name')->get());
    }

    public static function getAllForInvoice(int $invoiceId, ?string $taxName = null)
    {
        return static::whereHas('invoiceDetail', function ($query) use ($invoiceId) {
            $query->where('invoice_id', $invoiceId);
        })->when($taxName, fn($q) => $q->whereHas('tax', function ($query) use ($taxName) {
            $query->where('name', $taxName);
        })->withAggregate('tax', 'name')->get());
    }

    /* INTEGRITY */
    public static function columnsIntegrityCalculations()
    {
        return [
            'tax_amount' => DB::raw('get_updated_tax_amount_for_taxes(fin_invoice_detail_taxes.invoice_detail_id, fin_invoice_detail_taxes.tax_rate)'),
        ];
    }

    /* ELEMENTS */
}

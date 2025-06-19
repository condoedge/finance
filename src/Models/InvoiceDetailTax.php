<?php

namespace Condoedge\Finance\Models;

use Condoedge\Finance\Casts\SafeDecimalCast;
use Condoedge\Finance\Facades\InvoiceDetailService;
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
 * @property int $account_id Foreign key to fin_gl_accounts
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
        return $this->belongsTo(GlAccount::class, 'account_id');
    }

    public function tax()
    {
        return $this->belongsTo(Tax::class, 'tax_id');
    }

    /* ATTRIBUTES */

    /* CALCULATED FIELDS */
    public function getCompleteLabelAttribute()
    {
        return $this->tax->name . ' (' . $this->tax_rate->multiply(100) . '%)';
    }

    public function getCompleteLabelHtmlAttribute()
    {
        return '<span data-name="' . $this->tax->name . '" data-tax="' . $this->tax_rate . '" data-id="' . $this->tax_id . '">' . $this->complete_label . '</span>';
    }

    /* SCOPES */

    /* ACTIONS */
    /**
     * @deprecated Use InvoiceDetailService::applyTaxesToDetail() instead
     * Maintained for backward compatibility
     */
    public static function upsertForInvoiceDetailFromTax(UpsertTaxDetailDto $data)
    {
        $invoiceDetail = InvoiceDetailModel::findOrFail($data->invoice_detail_id);
        $taxes = InvoiceDetailService::applyTaxesToDetail($invoiceDetail, collect([$data->tax_id]));
        return $taxes->first();
    }

    /**
     * @deprecated Use InvoiceDetailService::applyTaxesToDetail() instead
     * Maintained for backward compatibility
     */
    public static function upsertManyForInvoiceDetail(UpsertManyTaxDetailDto $data)
    {
        $invoiceDetail = InvoiceDetailModel::findOrFail($data->invoice_detail_id);
        return InvoiceDetailService::applyTaxesToDetail($invoiceDetail, collect($data->taxes_ids ?? []));
    }

    /**
     * @deprecated Use InvoiceDetailService::getDetailTaxes() instead
     * Maintained for backward compatibility
     */
    public static function getAllForInvoiceDetail(int $invoiceDetailId, ?string $taxName = null)
    {
        $invoiceDetail = InvoiceDetailModel::findOrFail($invoiceDetailId);
        return InvoiceDetailService::getDetailTaxes($invoiceDetail, $taxName);
    }

    /**
     * @deprecated Use InvoiceDetailService::getInvoiceTaxes() instead
     * Maintained for backward compatibility
     */
    public static function getAllForInvoice(int $invoiceId, ?string $taxName = null)
    {
        $invoice = Invoice::findOrFail($invoiceId);
        return InvoiceDetailService::getInvoiceTaxes($invoice, $taxName);
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

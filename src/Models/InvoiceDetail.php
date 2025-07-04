<?php

namespace Condoedge\Finance\Models;

use Condoedge\Finance\Casts\SafeDecimalCast;
use Condoedge\Finance\Events\InvoiceDetailGenerated;
use Condoedge\Finance\Facades\InvoiceDetailService;
use Condoedge\Finance\Models\Dto\Invoices\CreateOrUpdateInvoiceDetail;
use Illuminate\Support\Facades\DB;

/**
 * Class InvoiceDetail
 *
 * @package Condoedge\Finance\Models
 *
 * @property int $id
 * @property int $invoice_id Foreign key to fin_invoices
 * @property int $revenue_account_id Foreign key to fin_gl_accounts
 * @property int|null $product_id Foreign key to fin_products
 * @property int $quantity
 * @property string $name
 * @property string $description
 * @property \Condoedge\Finance\Casts\SafeDecimal $unit_price Checked by get_detail_unit_price_with_sign() function. Ensuring sign is correct. It could be saved as negative or positive.
 * @property \Condoedge\Finance\Casts\SafeDecimal $extended_price @CALCULATED: Calculated as quantity * unit_price
 * @property \Condoedge\Finance\Casts\SafeDecimal $tax_amount @CALCULATED: Calculated using get_detail_tax_amount() function
 * @property \Condoedge\Finance\Casts\SafeDecimal $total_amount @CALCULATED: Calculated as extended_price + tax_amount
 * @property-read \Condoedge\Finance\Models\Invoice $invoice
 */
class InvoiceDetail extends AbstractMainFinanceModel
{
    protected $table = 'fin_invoice_details';

    protected $casts = [
        'unit_price' => SafeDecimalCast::class,
        'extended_price' => SafeDecimalCast::class,
        'tax_amount' => SafeDecimalCast::class,
        'total_amount' => SafeDecimalCast::class,
    ];

    public function getCreatedEventClass()
    {
        return InvoiceDetailGenerated::class;
    }

    public function save(array $options = [])
    {
        /**
         * WE ARE USING A DB TRIGGER TO CREATE TAXES FOR EACH DETAIL.
         *
         * @see tr_invoice_details_before_insert (insert_invoice_details_v0001.sql)
         */
        return parent::save($options);
    }

    /* RELATIONSHIPS */
    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function invoiceTaxes()
    {
        return $this->hasMany(InvoiceDetailTax::class, 'invoice_detail_id');
    }

    public function revenueAccount()
    {
        return $this->belongsTo(GlAccount::class, 'revenue_account_id');
    }

    /* ATTRIBUTES */

    /* CALCULATED FIELDS */

    /* SCOPES */
    public function scopeForInvoice($query, $invoiceId)
    {
        return $query->where('invoice_id', $invoiceId);
    }

    /* ACTIONS */
    /**
     * @deprecated Use InvoiceDetailService::createInvoiceDetail() instead
     * Maintained for backward compatibility
     */
    public static function createInvoiceDetail(CreateOrUpdateInvoiceDetail $dto)
    {
        return InvoiceDetailService::createInvoiceDetail($dto);
    }

    /**
     * @deprecated Use InvoiceDetailService::updateInvoiceDetail() instead
     * Maintained for backward compatibility
     */
    public static function editInvoiceDetail(CreateOrUpdateInvoiceDetail $dto)
    {
        return InvoiceDetailService::updateInvoiceDetail($dto);
    }

    /**
     * @deprecated Use InvoiceDetailService::validateCanModifyDetail() instead
     * Maintained for backward compatibility
     */
    public function deletable()
    {
        return $this->invoice->invoice_status_id == InvoiceStatusEnum::DRAFT;
    }

    /* INTEGRITY */
    public static function columnsIntegrityCalculations()
    {
        return [
            'unit_price' => DB::raw('get_detail_unit_price_with_sign(fin_invoice_details.id)'),
            'tax_amount' => DB::raw('get_detail_tax_amount(fin_invoice_details.id)'),
        ];
    }

    /* ELEMENTS */
}

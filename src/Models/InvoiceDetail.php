<?php

namespace Condoedge\Finance\Models;

use Condoedge\Finance\Events\InvoiceDetailGenerated;
use Illuminate\Support\Facades\DB;

/**
 * Class InvoiceDetail
 * 
 * @package Condoedge\Finance\Models
 * 
 * @property int $id
 * @property int $invoice_id Foreign key to fin_invoices
 * @property int $revenue_account_id Foreign key to fin_accounts
 * @property int|null $product_id Foreign key to fin_products
 * @property int $quantity
 * @property string $name
 * @property string $description
 * @property float $unit_price Checked by get_detail_unit_price_with_sign() function. Ensuring sign is correct. It could be saved as negative or positive.
 * @property float $extended_price Generated column: Calculated as quantity * unit_price
 * @property float $tax_amount Generated column: Calculated using get_detail_tax_amount() function
 * @property float $total_amount Generated column: Calculated as extended_price + tax_amount
 * 
 * @property-read \Condoedge\Finance\Models\Invoice $invoice
 */
class InvoiceDetail extends AbstractMainFinanceModel
{
    protected $table = 'fin_invoice_details';

    public function getCreatedEventClass()
    {
        return InvoiceDetailGenerated::class;
    }

    /* RELATIONSHIPS */
    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    /* ATTRIBUTES */

    /* CALCULATED FIELDS */

    /* SCOPES */

    /* ACTIONS */

    /* INTEGRITY */
    public static function checkIntegrity($ids = null): void
    {
        DB::table('fin_invoice_details')
            ->when($ids, function ($query) use ($ids) {
                $query->whereIn('id', $ids);
            })->update([
                'unit_price' => DB::raw('get_detail_unit_price_with_sign(fin_invoice_details.id)'),
                'tax_amount' => DB::raw('get_detail_tax_amount(fin_invoice_details.id)'),
            ]);
    }

    /* ELEMENTS */    
}
<?php

namespace Condoedge\Finance\Models;

use Condoedge\Finance\Events\InvoiceDetailGenerated;
use Condoedge\Finance\Models\Dto\CreateOrUpdateInvoiceDetail;
use Illuminate\Support\Facades\DB;
use Kompo\Auth\Models\Teams\PermissionTypeEnum;

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
 * @property float $extended_price @CALCULATED: Calculated as quantity * unit_price
 * @property float $tax_amount @CALCULATED: Calculated using get_detail_tax_amount() function
 * @property float $total_amount @CALCULATED: Calculated as extended_price + tax_amount
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
        return $this->belongsTo(Account::class, 'revenue_account_id');
    }

    /* ATTRIBUTES */

    /* CALCULATED FIELDS */

    /* SCOPES */

    /* ACTIONS */
    public static function createInvoiceDetail(CreateOrUpdateInvoiceDetail $dto)
    {
        $invoiceDetail = new self();
        $invoiceDetail->invoice_id = $dto->invoice_id;
        $invoiceDetail->name = $dto->name;
        $invoiceDetail->revenue_account_id = $dto->revenue_account_id;
        $invoiceDetail->product_id = $dto->product_id;
        $invoiceDetail->quantity = $dto->quantity;
        $invoiceDetail->name = $dto->name;
        $invoiceDetail->description = $dto->description;
        $invoiceDetail->unit_price = $dto->unit_price;
        $invoiceDetail->save();

        return $invoiceDetail;
    }

    public static function editInvoiceDetail(CreateOrUpdateInvoiceDetail $dto)
    {
        $invoiceDetail = self::findOrFail($dto->id);
        $invoiceDetail->name = $dto->name;
        $invoiceDetail->revenue_account_id = $dto->revenue_account_id;
        $invoiceDetail->product_id = $dto->product_id;
        $invoiceDetail->quantity = $dto->quantity;
        $invoiceDetail->name = $dto->name;
        $invoiceDetail->description = $dto->description;
        $invoiceDetail->unit_price = $dto->unit_price;
        $invoiceDetail->save();

        return $invoiceDetail;
    }

    public function deletable()
    {
        return $this->invoice->invoice_status_id == InvoiceStatusEnum::DRAFT && auth()->user()->hasPermission('InvoiceDetail', PermissionTypeEnum::WRITE, $this->invoice->customer->team_id);
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
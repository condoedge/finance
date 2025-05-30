<?php

namespace Condoedge\Finance\Models\Payable;

use Condoedge\Finance\Casts\SafeDecimalCast;
use Condoedge\Finance\Facades\BillDetailModel;
use Condoedge\Finance\Facades\TaxModel;
use Condoedge\Finance\Models\AbstractMainFinanceModel;
use Condoedge\Finance\Models\Account;
use Condoedge\Finance\Models\Tax;
use Condoedge\Finance\Models\Dto\Taxes\UpsertManyTaxDetailDto;
use Condoedge\Finance\Models\Dto\Taxes\UpsertTaxDetailDto;
use Illuminate\Support\Facades\DB;

/**
 * Class BillDetailTax
 * 
 * @package Condoedge\Finance\Models\Payable
 * 
 * @TRIGGERED BY: tr_bill_details_after_insert (insert_bill_taxes_v0001.sql)
 * 
 * @property int $id
 * @property int $bill_detail_id Foreign key to fin_bill_details
 * @property int $account_id Foreign key to fin_accounts
 * @property int $tax_id Foreign key to the original tax fin_taxes. The tax rate can mismatch if it was changed
 * @property int|null $tax_amount Tax amount 
 * @property \Condoedge\Finance\Casts\SafeDecimal $tax_rate Tax rate as percentage / 100
 * 
 * @property-read \Condoedge\Finance\Models\Payable\Bill $bill
 */
class BillDetailTax extends AbstractMainFinanceModel
{
    protected $table = 'fin_bill_detail_taxes';

    protected $casts = [
        'tax_rate' => SafeDecimalCast::class,
        'tax_amount' => SafeDecimalCast::class,
    ];

    /* RELATIONSHIPS */
    public function billDetail()
    {
        return $this->belongsTo(BillDetail::class, 'bill_detail_id');
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
    public static function upsertForBillDetailFromTax(UpsertTaxDetailDto $data)
    {
        if ($billDetailTax = static::where('bill_detail_id', $data->bill_detail_id)->where('tax_id', $data->tax_id)->first()) {
            return $billDetailTax;
        }

        $tax = TaxModel::findOrFail($data->tax_id);
        $billDetail = BillDetailModel::findOrFail($data->bill_detail_id);

        $billDetailTax = new self();
        $billDetailTax->bill_detail_id = $data->bill_detail_id;
        $billDetailTax->tax_id = $tax->id;
        $billDetailTax->tax_rate = $tax->rate;
        // It will be recalculated so it doesn't matter
        $billDetailTax->tax_amount = $billDetail->extended_price->multiply($tax->rate);
        $billDetailTax->save();

        return $billDetailTax;
    }

    public static function upsertManyForBillDetail(UpsertManyTaxDetailDto $data)
    {
        foreach (($data->taxes_ids ?? []) as $taxId) {
            BillDetailTax::upsertForBillDetailFromTax(new UpsertTaxDetailDto([
                'bill_detail_id' => $data->bill_detail_id,
                'tax_id' => $taxId,
            ]));
        }

        $billDetail = BillDetailModel::findOrFail($data->bill_detail_id);

        $billDetail->billTaxes()->whereNotIn('tax_id', $data->taxes_ids ?? [])->get()->each->delete();
    }

    public static function getAllForBillDetail(int $billDetailId, ?string $taxName = null)
    {
        return static::where('bill_detail_id', $billDetailId)
            ->when($taxName, fn($q) => $q->whereHas('tax', function ($query) use ($taxName) {
                $query->where('name', $taxName);
            })->withAggregate('tax', 'name')->get());
    }

    public static function getAllForBill(int $billId, ?string $taxName = null)
    {
        return static::whereHas('billDetail', function ($query) use ($billId) {
            $query->where('bill_id', $billId);
        })->when($taxName, fn($q) => $q->whereHas('tax', function ($query) use ($taxName) {
            $query->where('name', $taxName);
        })->withAggregate('tax', 'name')->get());
    }

    /* INTEGRITY */
    public static function columnsIntegrityCalculations()
    {
        return [
            'tax_amount' => DB::raw('get_updated_bill_tax_amount_for_taxes(fin_bill_detail_taxes.bill_detail_id, fin_bill_detail_taxes.tax_rate)'),
        ];
    }

    /* ELEMENTS */
}

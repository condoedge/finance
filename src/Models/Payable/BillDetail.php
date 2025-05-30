<?php

namespace Condoedge\Finance\Models\Payable;

use Condoedge\Finance\Casts\SafeDecimalCast;
use Condoedge\Finance\Events\BillDetailGenerated;
use Condoedge\Finance\Models\AbstractMainFinanceModel;
use Condoedge\Finance\Models\Account;
use Condoedge\Finance\Models\Dto\Bills\CreateOrUpdateBillDetail;
use Condoedge\Finance\Models\Dto\Taxes\UpsertManyTaxDetailDto;
use Illuminate\Support\Facades\DB;
use Kompo\Auth\Models\Teams\PermissionTypeEnum;

/**
 * Class BillDetail
 * 
 * @package Condoedge\Finance\Models\Payable
 * 
 * @property int $id
 * @property int $bill_id Foreign key to fin_bills
 * @property int $expense_account_id Foreign key to fin_accounts
 * @property int|null $product_id Foreign key to fin_products
 * @property int $quantity
 * @property string $name
 * @property string $description
 * @property \Condoedge\Finance\Casts\SafeDecimal $unit_price Checked by get_bill_detail_unit_price_with_sign() function. Ensuring sign is correct. It could be saved as negative or positive.
 * @property \Condoedge\Finance\Casts\SafeDecimal $extended_price @CALCULATED: Calculated as quantity * unit_price
 * @property \Condoedge\Finance\Casts\SafeDecimal $tax_amount @CALCULATED: Calculated using get_bill_detail_tax_amount() function
 * @property \Condoedge\Finance\Casts\SafeDecimal $total_amount @CALCULATED: Calculated as extended_price + tax_amount
 * 
 * @property-read \Condoedge\Finance\Models\Payable\Bill $bill
 */
class BillDetail extends AbstractMainFinanceModel
{
    protected $table = 'fin_bill_details';

    protected $casts = [
        'unit_price' => SafeDecimalCast::class,
        'extended_price' => SafeDecimalCast::class,
        'tax_amount' => SafeDecimalCast::class,
        'total_amount' => SafeDecimalCast::class,
    ];

    public function getCreatedEventClass()
    {
        return BillDetailGenerated::class;
    }

    public function save(array $options = [])
    {
        /**
         * WE ARE USING A DB TRIGGER TO CREATE TAXES FOR EACH DETAIL.
         * 
         * @see tr_bill_details_before_insert (insert_bill_details_v0001.sql)
         */
        return parent::save($options);
    }

    /* RELATIONSHIPS */
    public function bill()
    {
        return $this->belongsTo(Bill::class, 'bill_id');
    }

    public function billTaxes()
    {
        return $this->hasMany(BillDetailTax::class, 'bill_detail_id');
    }

    public function expenseAccount()
    {
        return $this->belongsTo(Account::class, 'expense_account_id');
    }

    /* ATTRIBUTES */

    /* CALCULATED FIELDS */

    /* SCOPES */

    /* ACTIONS */
    public static function createBillDetail(CreateOrUpdateBillDetail $dto)
    {
        $billDetail = new self();
        $billDetail->bill_id = $dto->bill_id;
        $billDetail->name = $dto->name;
        $billDetail->expense_account_id = $dto->expense_account_id;
        $billDetail->product_id = $dto->product_id;
        $billDetail->quantity = $dto->quantity;
        $billDetail->name = $dto->name;
        $billDetail->description = $dto->description;
        $billDetail->unit_price = $dto->unit_price;
        $billDetail->save();

        BillDetailTax::upsertManyForBillDetail(new UpsertManyTaxDetailDto([
            'taxes_ids' => $dto->taxesIds ?? [],
            'bill_detail_id' => $billDetail->id,
        ]));

        return $billDetail;
    }

    public static function editBillDetail(CreateOrUpdateBillDetail $dto)
    {
        $billDetail = self::findOrFail($dto->id);
        $billDetail->name = $dto->name;
        $billDetail->expense_account_id = $dto->expense_account_id;
        $billDetail->product_id = $dto->product_id;
        $billDetail->quantity = $dto->quantity;
        $billDetail->name = $dto->name;
        $billDetail->description = $dto->description;
        $billDetail->unit_price = $dto->unit_price;
        $billDetail->save();

        BillDetailTax::upsertManyForBillDetail(new UpsertManyTaxDetailDto([
            'taxes_ids' => $dto->taxesIds ?? [],
            'bill_detail_id' => $billDetail->id,
        ]));

        return $billDetail;
    }

    public function deletable()
    {
        return $this->bill->bill_status_id == BillStatusEnum::DRAFT && auth()->user()->hasPermission('BillDetail', PermissionTypeEnum::WRITE, $this->bill->vendor->team_id);
    }

    /* INTEGRITY */
    public static function columnsIntegrityCalculations()
    {
        return [
            'unit_price' => DB::raw('get_bill_detail_unit_price_with_sign(fin_bill_details.id)'),
            'tax_amount' => DB::raw('get_bill_detail_tax_amount(fin_bill_details.id)'),
        ];
    }

    /* ELEMENTS */
}

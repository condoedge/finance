<?php

namespace Condoedge\Finance\Models\Payable;

use Condoedge\Finance\Casts\SafeDecimalCast;
use Condoedge\Finance\Models\AbstractMainFinanceModel;
use Condoedge\Finance\Models\ApplicableToInvoiceContract;
use Illuminate\Support\Facades\DB;
use Condoedge\Finance\Models\Dto\Bills\ApplicableRecordDto;
use Condoedge\Finance\Models\Dto\Payments\CreateAppliesForMultipleBillDto;
use Condoedge\Finance\Models\Dto\Payments\CreateApplyForBillDto;

/**
 * Class BillApply
 * 
 * @description This model represents the application of a payment/credit to a bill.
 * 
 * @package Condoedge\Finance\Models\Payable
 * 
 * @property int $id
 * @property int $bill_id Foreign key to fin_bills
 * @property string|\Carbon $apply_date The date of the payment application
 * @property int $applicable_id The ID of the applicable record (e.g., bill, credit, etc.)
 * @property int $applicable_type The type of the applicable record (e.g., payment = 1, credit = 2, etc.)
 * @property \Condoedge\Finance\Casts\SafeDecimal $payment_applied_amount The amount of the payment applied to the bill
 * 
**/ 
class BillApply extends AbstractMainFinanceModel
{
    protected $table = 'fin_bill_applies';

    protected $casts = [
        'apply_date' => 'date',
        'payment_applied_amount' => SafeDecimalCast::class,
    ];

    /* RELATIONSHIPS */
    public function bill()
    {
        return $this->belongsTo(Bill::class, 'bill_id');
    }

    public function applicable()
    {
        return $this->morphTo();
    }

    // ACTIONS
    public static function createForMultipleBills(CreateAppliesForMultipleBillDto $data)
    {
        $bills = $data->amounts_to_apply;

        $paymentsCreated = [];

        foreach ($bills as $bill) {
            $paymentsCreated[] = self::createForBill(new CreateApplyForBillDto([
                'bill_id' => $bill['id'],
                'apply_date' => $data->apply_date,
                'applicable' => $data->applicable,
                'applicable_type' => $data->applicable_type,
                'amount_applied' => $bill['amount_applied'],
            ]));
        }

        return $paymentsCreated;
    }

    public static function createForBill(CreateApplyForBillDto $data)
    {
        $billPayment = new self();
        $billPayment->bill_id = $data->bill_id;
        $billPayment->payment_applied_amount = $data->amount_applied;
        $billPayment->apply_date = $data->apply_date;
        $billPayment->applicable_id = $data->applicable->id;
        $billPayment->applicable_type = $data->applicable_type;
        $billPayment->save();

        return $billPayment;
    }

    /**
     * Returns the required information of all applicable records to apply payments to bills.
     * 
     * Each item in the returned collection is a stdClass with:
     * @property-read float $applicable_amount_left The remaining amount that can be applied.
     * @property-read string $applicable_name A human-readable name or description of the applicable record.
     *
     * @return \Illuminate\Support\Collection<int, ApplicableRecordDto>
     */
    public static function getAllApplicablesRecords($vendorId = null)
    {
        $types = self::getAllApplicablesTypes();
        $query = null;

        foreach ($types as $type) {
            $type = new $type();

            $selectRaw = $type::getApplicableAmountLeftColumn() . ' as applicable_amount_left, '
                . $type::getApplicableNameRawQuery() . ' as applicable_name, id as applicable_id, '
                . $type::getApplicableType() . ' as applicable_type';

            if ($query === null) {
                $query = $type::selectRaw($selectRaw)
                    ->applicable($vendorId);
            } else {
                $query->union(
                    $type::selectRaw($selectRaw)
                        ->applicable($vendorId),
                );
            }
        }

        if (!$query) {
            return collect();
        }

        return DB::table($query)->where('applicable_amount_left', '>', 0)->whereNotNull('applicable_amount_left')->get();
    }

    public static function getAllApplicablesTypes()
    {
        $types = config('kompo-finance.bill_applicable_types');

        foreach ($types as $type) {
            if (!in_array(ApplicableToInvoiceContract::class, class_implements($type))) {
                throw new \Exception("Class $type does not implement the required interface.");
            }
        }

        return $types;
    }

    /* INTEGRITY */
    public static function columnsIntegrityCalculations()
    {
        return [
            'payment_applied_amount' => DB::raw('get_bill_payment_applied_amount_with_sign(fin_bill_applies.id)'),
        ];
    }
}

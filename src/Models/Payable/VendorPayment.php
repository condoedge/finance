<?php

namespace Condoedge\Finance\Models\Payable;

use Condoedge\Finance\Casts\SafeDecimalCast;
use Condoedge\Finance\Facades\BillPaymentModel;
use Condoedge\Finance\Models\AbstractMainFinanceModel;
use Condoedge\Finance\Models\ApplicableToInvoiceContract;
use Condoedge\Finance\Models\Dto\Payments\CreateApplyForBillDto;
use Condoedge\Finance\Models\Dto\Payments\CreateVendorPaymentDto;
use Condoedge\Finance\Models\Dto\Payments\CreateVendorPaymentForBillDto;
use Condoedge\Finance\Models\MorphablesEnum;
use Illuminate\Support\Facades\DB;

/**
 * Class VendorPayment
 * 
 * @description This model represents payments to vendors as separated entities (before being applied to bills).
 * 
 * @package Condoedge\Finance\Models\Payable
 * 
 * @property int $id
 * @property int $vendor_id Foreign key to fin_vendors
 * @property \DateTime $payment_date The date of the payment
 * @property \Condoedge\Finance\Casts\SafeDecimal $amount The total amount of the payment
 * @property \Condoedge\Finance\Casts\SafeDecimal $amount_left @CALCULATED BY calculate_vendor_payment_amount_left() - Amount left to be applied to bills
 * 
**/ 
class VendorPayment extends AbstractMainFinanceModel implements ApplicableToInvoiceContract
{
    use \Condoedge\Finance\Models\Traits\ApplicableUtilsTrait;
    
    protected $table = 'fin_vendor_payments';

    protected $casts = [
        'payment_date' => 'date',
        'amount' => SafeDecimalCast::class,
        'amount_left' => SafeDecimalCast::class,
    ];

    // ACTIONS
    public static function createForVendor(CreateVendorPaymentDto $data)
    {
        $vendorPayment = new self();
        $vendorPayment->vendor_id = $data->vendor_id;
        $vendorPayment->payment_date = $data->payment_date;
        $vendorPayment->amount = $data->amount;
        $vendorPayment->save();

        return $vendorPayment;
    }

    public static function createForVendorAndApply(CreateVendorPaymentForBillDto $data)
    {
        try {
            $payment = static::createForVendor(new CreateVendorPaymentDto($data->toArray()));

            BillPaymentModel::createForBill(new CreateApplyForBillDto([
                'bill_id' => $data->bill_id,
                'applicable' => $payment,
                'apply_date' => now(),
                'amount_applied' => $payment->amount,
                'applicable_type' => MorphablesEnum::VENDOR_PAYMENT->value,
            ]));
        } catch (\Exception $e) {
            // Rollback the payment creation if bill payment fails
            $payment->delete();
            throw $e;
        }
    }

    // SCOPES
    public function scopeForVendor($query, $vendorId)
    {
        return $query->where('vendor_id', $vendorId);
    }

    public function scopeHasAmountLeft($query)
    {
        // We can use amount_left column directly. I'm using the function as double check of integrity.
        return $query->whereRaw('calculate_vendor_payment_amount_left(fin_vendor_payments.id) > 0');
    }

    public static function columnsIntegrityCalculations()
    {
        return [
            'amount_left' => DB::raw('calculate_vendor_payment_amount_left(fin_vendor_payments.id)'),
        ];
    }

    // APPLICABLE TO BILL CONTRACT
    public static function getApplicableAmountLeftColumn(): string
    {
        return 'amount_left';
    }

    public static function getApplicableNameRawQuery(): string
    {
        return 'CONCAT("VPMT #", fin_vendor_payments.id)';
    }

    public static function getApplicableTotalAmountColumn(): string
    {
        return 'amount';
    }

    public static function scopeApplicable($builder, $vendorId = null)
    {
        return $builder->when($vendorId, function ($query) use ($vendorId) {
            return $query->where('vendor_id', $vendorId);
        })->where('amount_left', '>', 0);
    }
}

<?php

namespace Condoedge\Finance\Models;

use Condoedge\Finance\Facades\InvoicePaymentModel;
use Condoedge\Finance\Models\Dto\CreateApplyForInvoiceDto;
use Condoedge\Finance\Models\Dto\CreateCustomerPaymentDto;
use Condoedge\Finance\Models\Dto\CreateCustomerPaymentForInvoiceDto;
use Illuminate\Support\Facades\DB;

/**
 * Class CustomerPayment
 * 
 * @description This model represents payments of customer as separated entities (before being applied to invoices).
 * 
 * @package Condoedge\Finance\Models
 * 
 * @property int $id
 * @property int $customer_id Foreign key to fin_customers
 * @property \DateTime $payment_date The date of the payment
 * @property float $amount The total amount of the payment
 * @property float $amount_left @CALCULATED BY calculate_payment_amount_left() - Amount left to be applied to invoices
 * 
**/ 
class CustomerPayment extends AbstractMainFinanceModel implements ApplicableToInvoiceContract
{
    use \Condoedge\Finance\Models\Traits\ApplicableUtilsTrait;
    
    protected $table = 'fin_customer_payments';

    protected $casts = [
        'payment_date' => 'date',
    ];

    // ACTIONS
    public static function createForCustomer(CreateCustomerPaymentDto $data)
    {
        $customerPayment = new self();
        $customerPayment->customer_id = $data->customer_id;
        $customerPayment->payment_date = $data->payment_date;
        $customerPayment->amount = $data->amount;
        $customerPayment->save();

        return $customerPayment;
    }

    public static function createForCustomerAndApply(CreateCustomerPaymentForInvoiceDto $data)
    {
        try {
            $payment = static::createForCustomer(new CreateCustomerPaymentDto($data->toArray()));

            InvoicePaymentModel::createForInvoice(new CreateApplyForInvoiceDto([
                'invoice_id' => $data->invoice_id,
                'applicable' => $payment,
                'apply_date' => now(),
                'amount_applied' => $payment->amount,
                'applicable_type' => MorphablesEnum::PAYMENT->value,
            ]));
        } catch (\Exception $e) {
            // Rollback the payment creation if invoice payment fails
            $payment->delete();
            throw $e;
        }
    }

    // SCOPES
    public function scopeForCustomer($query, $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeHasAmountLeft($query)
    {
        // We can use amount_left column directly. I'm using the function as double check of integrity.
        return $query->whereRaw('calculate_payment_amount_left(fin_customer_payments.id) > 0');
    }

    public static function columnsIntegrityCalculations()
    {
        return [
            'amount_left' => DB::raw('calculate_payment_amount_left(fin_customer_payments.id)'),
        ];
    }

    // APPLICABLE TO INVOICE CONTRACT
    public static function getApplicableAmountLeftColumn(): string
    {
        return 'amount_left';
    }

    public static function getApplicableNameRawQuery(): string
    {
        return 'CONCAT("PMT #", fin_customer_payments.id)';
    }

    public static function getApplicableTotalAmountColumn(): string
    {
        return 'amount';
    }

    public static function scopeApplicable($builder, $customerId = null)
    {
        return $builder->when($customerId, function ($query) use ($customerId) {
            return $query->where('customer_id', $customerId);
        })->where('amount_left', '>', 0);
    }
}
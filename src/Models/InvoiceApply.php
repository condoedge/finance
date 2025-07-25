<?php

namespace Condoedge\Finance\Models;

use Condoedge\Finance\Casts\SafeDecimalCast;
use Condoedge\Finance\Models\Dto\Invoices\ApplicableRecordDto;
use Condoedge\Finance\Models\GlobalScopesTypes\Credit;
use Illuminate\Support\Facades\DB;

/**
 * Class InvoiceApply
 *
 * @description This model represents the applyment of a payment/credit to an invoice.
 *
 * @package Condoedge\Finance\Models
 *
 * @property int $id
 * @property int $invoice_id Foreign key to fin_invoices
 * @property string|\Carbon $apply_date The date of the payment application
 * @property int $applicable_id The ID of the applicable record (e.g., invoice, credit, etc.)
 * @property int $applicable_type The type of the applicable record (e.g., payment = 1, credit = 2, etc.)
 * @property \Condoedge\Finance\Casts\SafeDecimal $payment_applied_amount The amount of the payment applied to the invoice
 * 
 * @property-read Credit|CustomerPayment $applicable
 * @property-read Invoice $invoice The invoice to which the payment is applied
 *
 **/
class InvoiceApply extends AbstractMainFinanceModel
{
    protected $table = 'fin_invoice_applies';

    protected $casts = [
        'apply_date' => 'date',
        'payment_applied_amount' => SafeDecimalCast::class,
    ];

    /* RELATIONSHIPS */
    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    // You can apply a credit to an invoice, so this morphTo relationship is used to link the applicable record and reload with the integrity checker.
    public function credit()
    {
        return $this->morphTo(Invoice::class, 'applicable');
    }

    public function applicable()
    {
        return $this->morphTo();
    }

    // CALCULATED ACTIONS
    public function getPaymentMethod()
    {
        return $this->applicable?->paymentTrace?->paymentMethod ?? $this->applicable?->paymentMethod ?? $this->invoice->paymentMethod;
    }

    // ACTIONS

    /**
     * Returns the required information of all applicable records to apply payments to invoices.
     *
     * Each item in the returned collection is a stdClass with:
     *
     * @property-read float $applicable_amount_left The remaining amount that can be applied.
     * @property-read string $applicable_name A human-readable name or description of the applicable record.
     *
     * @return \Illuminate\Support\Collection<int, ApplicableRecordDto>
     */
    public static function getAllApplicablesRecords($customerId = null)
    {
        $types = static::getAllApplicablesTypes();
        $query = null;

        foreach ($types as $type) {
            $type = new $type();

            $selectRaw = $type::getApplicableAmountLeftColumn() . ' as applicable_amount_left, '
                . $type::getApplicableNameRawQuery() . ' as applicable_name, id as applicable_id, '
                . $type::getApplicableType() . ' as applicable_type';

            if ($query === null) {
                $query = $type::selectRaw($selectRaw)
                    ->applicable($customerId);
            } else {
                $query->union(
                    $type::selectRaw($selectRaw)
                        ->applicable($customerId),
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
        $types = config('kompo-finance.invoice_applicable_types');

        foreach ($types as $type) {
            if (!in_array(ApplicableToInvoiceContract::class, class_implements($type), true)) {
                throw new \Exception("Class $type does not implement the required interface.");
            }
        }

        return $types;
    }

    /* INTEGRITY */
    public static function columnsIntegrityCalculations()
    {
        return [
            'payment_applied_amount' => DB::raw('get_payment_applied_amount_with_sign(fin_invoice_applies.id)'),
        ];
    }
}

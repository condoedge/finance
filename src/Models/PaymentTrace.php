<?php

namespace Condoedge\Finance\Models;

use Condoedge\Utils\Models\Model;

/**
 * This class is just to follow the payment trace but it's not required for the balance, or any other financial calculations.
 */
class PaymentTrace extends Model
{
    protected $table = 'fin_payment_traces';
    
    // RELATIONS
    public function payable()
    {
        return $this->morphTo();
    }

    // SCOPES
    public function scopeForPayable($query, $payableId, $payableType)
    {
        return $query->where('payable_id', $payableId)
                     ->where('payable_type', $payableType);
    }

    public function scopeForExternalReference($query, $externalTransactionRef)
    {
        return $query->where('external_transaction_ref', $externalTransactionRef);
    }

    // ACTIONS
    public static function createOrUpdateTrace(
        string $externalTransactionRef,
        PaymentTraceStatusEnum $status,
        ?int $payableId = null,
        ?string $payableType = null
    ): self {
        return self::updateOrCreate(
            [
                'external_transaction_ref' => $externalTransactionRef,
                'payable_id' => $payableId,
                'payable_type' => $payableType,
            ],
            [
                'status' => $status->value,
            ]
        );
    }
}
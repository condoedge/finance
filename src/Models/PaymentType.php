<?php

namespace Condoedge\Finance\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Condoedge\Utils\Models\Model;

/**
 * Payment Type Model
 * 
 * Represents the different types of payment methods available in the system.
 * This model is linked to the PaymentTypeEnum for consistent type definitions.
 */
class PaymentType extends Model
{
    use HasFactory;

    protected $table = 'fin_payment_types';

    protected $fillable = [
        'id',
        'name',
        'payment_gateway',
    ];

    /**
     * Get the enum instance for this payment type
     * 
     * @return PaymentTypeEnum
     */
    public function getEnum(): PaymentTypeEnum
    {
        return PaymentTypeEnum::from($this->id);
    }
}

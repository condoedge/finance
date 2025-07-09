<?php

namespace Condoedge\Finance\Models;

use Condoedge\Finance\Facades\PaymentMethodEnum;
use Condoedge\Utils\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Payment Type Model
 *
 * Represents the different types of payment methods available in the system.
 * This model is linked to the PaymentTypeEnum for consistent type definitions.
 */
class PaymentMethod extends Model
{
    use HasFactory;

    protected $table = 'fin_payment_methods';

    protected $fillable = [
        'id',
        'name',
    ];

    /**
     * Get the enum instance for this payment type
     *
     * @return PaymentMethodEnum
     */
    public function getEnum(): PaymentMethodEnum
    {
        return PaymentMethodEnum::from($this->id);
    }

    public function scopeIsOnlinePayment($query)
    {
        return $query->whereIn('id', collect(PaymentMethodEnum::cases())->filter(function ($enum) {
            return $enum->online();
        })->pluck('value')->toArray());
    }
}

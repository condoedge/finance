<?php

namespace Condoedge\Finance\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Condoedge\Utils\Models\Model;

class PaymentInstallment extends Model
{
    use HasFactory;

    protected $table = 'fin_payment_installments';

    protected $fillable = [
        'id',
        'name',
    ];

    /**
     * Get the enum instance for this payment type
     * 
     * @return PaymentInstallmentEnum
     */
    public function getEnum(): PaymentInstallmentEnum
    {
        return PaymentInstallmentEnum::from($this->id);
    }
}

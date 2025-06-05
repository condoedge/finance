<?php

namespace Condoedge\Finance\Models\Traits;

use Condoedge\Finance\Casts\SafeDecimal;
use Condoedge\Finance\Models\ApplicableToInvoiceContract;
use Condoedge\Finance\Models\MorphablesEnum;

trait ApplicableUtilsTrait
{
    use HasSqlColumnCalculation;

    public static function bootApplicableUtilsTrait()
    {
        if (!in_array(ApplicableToInvoiceContract::class, class_implements(static::class))) {
            throw new \RuntimeException('ApplicableUtilsTrait must be used with ApplicableToInvoiceContract');
        }
    }

    public static function getApplicableType(): string
    {
        return MorphablesEnum::getFromM(new static)->value;
    }

    public function getApplicableAmountLeftAttribute(): SafeDecimal
    {
        return new SafeDecimal($this->getSqlColumnCalculation(static::getApplicableAmountLeftColumn(), 'amount_left'));
    }

    public function getApplicableTotalAmountAttribute(): SafeDecimal
    {
        return new SafeDecimal($this->getSqlColumnCalculation(static::getApplicableTotalAmountColumn(), 'total_amount'));
    }
}
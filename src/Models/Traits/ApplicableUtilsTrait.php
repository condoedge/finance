<?php

namespace Condoedge\Finance\Models\Traits;

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

    public function getApplicableAmountLeftAttribute(): float|int
    {
        return $this->getSqlColumnCalculation(static::getApplicableAmountLeftColumn(), 'amount_left');
    }

    public function getApplicableTotalAmountAttribute(): float|int
    {
        return $this->getSqlColumnCalculation(static::getApplicableTotalAmountColumn(), 'total_amount');
    }
}
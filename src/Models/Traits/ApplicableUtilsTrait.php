<?php

namespace Condoedge\Finance\Models\Traits;

use Condoedge\Finance\Casts\SafeDecimal;
use Condoedge\Finance\Casts\SafeDecimalCast;
use Condoedge\Finance\Models\ApplicableToInvoiceContract;
use Condoedge\Finance\Models\MorphablesEnum;

trait ApplicableUtilsTrait
{
    use HasSqlColumnCalculation;

    public static function bootApplicableUtilsTrait()
    {
        if (!in_array(ApplicableToInvoiceContract::class, class_implements(static::class), true)) {
            throw new \RuntimeException('ApplicableUtilsTrait must be used with ApplicableToInvoiceContract');
        }
    }

    /**
     * The aliases the accessors below read back. Credit has no such columns, so without
     * these casts the raw decimal reaches getAttribute and trips the unmanaged-decimal guard.
     */
    public function initializeApplicableUtilsTrait()
    {
        $this->mergeCasts([
            'amount_left' => SafeDecimalCast::class,
            'total_amount' => SafeDecimalCast::class,
        ]);
    }

    public static function getApplicableType(): string
    {
        return MorphablesEnum::getFromM(new static())->value;
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

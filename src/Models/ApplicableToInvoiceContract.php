<?php

namespace Condoedge\Finance\Models;

use Condoedge\Finance\Casts\SafeDecimal;

interface ApplicableToInvoiceContract
{
    public static function getApplicableType(): string;

    public static function getApplicableNameRawQuery(): string;

    public static function getApplicableAmountLeftColumn(): string;

    public static function getApplicableTotalAmountColumn(): string;

    public static function scopeApplicable($builder, $customerId = null);

    public function getSqlColumnCalculation($column, $as = null);

    public function getApplicableAmountLeftAttribute(): SafeDecimal;

    public function getApplicableTotalAmountAttribute(): SafeDecimal;
}

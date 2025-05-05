<?php

namespace Condoedge\Finance\Models;

interface ApplicableToInvoiceContract
{
    public static function getApplicableType(): string;

    public static function getApplicableNameRawQuery(): string;

    public static function getApplicableAmountLeftColumn(): string;

    public static function getApplicableTotalAmountColumn(): string;

    public static function scopeApplicable($builder, $customerId = null);

    public function getSqlColumnCalculation($column, $as = null);

    public function getApplicableAmountLeftAttribute(): float|int;

    public function getApplicableTotalAmountAttribute(): float|int;
}
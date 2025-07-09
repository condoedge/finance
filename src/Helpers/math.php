<?php

use App\Helpers\MathHelper;
use Condoedge\Finance\Casts\SafeDecimal;
use Illuminate\Support\Collection;

if (!function_exists('float_equals')) {
    function float_equals($num1, $num2, $scale = 5)
    {
        return MathHelper::floatEquals($num1, $num2, $scale);
    }
}

if (!function_exists('safe_decimal')) {
    function safe_decimal($num, $scale = 5): float
    {
        return MathHelper::safeDecimal($num, $scale);
    }
}

if (!function_exists('safeDecimal')) {
    function safeDecimal($num): SafeDecimal
    {
        return new SafeDecimal($num);
    }
}

if (!function_exists('is_decimal')) {
    function is_decimal($val)
    {
        return is_numeric($val) && floor($val) != $val;
    }
}

// DECIMALS MACROS

Collection::macro('sumDecimals', function ($key = null) {
    return $this->reduce(function (?SafeDecimal $carry, $item) use ($key) {
        if (is_callable($key)) {
            $value = $key($item);
        } else {
            $value = $key ? data_get($item, $key) : $item;
        }

        $decimal = $value instanceof SafeDecimal ? $value : new SafeDecimal($value);
        return $carry ? $carry->add($decimal) : $decimal;
    });
});

Collection::macro('avgDecimals', function ($key = null) {
    $count = $this->count();
    if ($count === 0) {
        return new SafeDecimal('0');
    }

    $sum = $this->sumDecimals($key);
    return $sum->divide(new SafeDecimal((string) $count));
});

Collection::macro('maxDecimal', function ($key = null) {
    return $this->map(function ($item) use ($key) {
        $value = $key ? data_get($item, $key) : $item;
        return $value instanceof SafeDecimal ? $value : new SafeDecimal($value);
    })->reduce(function (?SafeDecimal $carry, SafeDecimal $item) {
        if (is_null($carry)) {
            return $item;
        }
        return $item->greaterThan($carry) ? $item : $carry;
    });
});

Collection::macro('minDecimal', function ($key = null) {
    return $this->map(function ($item) use ($key) {
        $value = $key ? data_get($item, $key) : $item;
        return $value instanceof SafeDecimal ? $value : new SafeDecimal($value);
    })->reduce(function (?SafeDecimal $carry, SafeDecimal $item) {
        if (is_null($carry)) {
            return $item;
        }
        return $item->lessThan($carry) ? $item : $carry;
    });
});

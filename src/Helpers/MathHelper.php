<?php

namespace App\Helpers;

class MathHelper
{
    /**
     * Compare two numbers with a given precision.
     *
     * @param float|string $num1
     * @param float|string $num2
     * @param int $scale Number of decimal places to compare
     * @return bool True if equal, false otherwise
     */
    public static function floatEquals($num1, $num2, $scale = 5)
    {
        return bccomp((string)$num1, (string)$num2, $scale) === 0;
    }

    /**
     * Give a number a fixed precision.
     *
     * @param float|string $num Number to round
     * @param int $scale Number of decimal places to compare
     * @return float Rounded number 
     */
    public static function safeDecimal($num, $scale = 5): float
    {
        return round((float) $num, $scale);
    }
}
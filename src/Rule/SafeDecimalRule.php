<?php

namespace Condoedge\Finance\Rule;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SafeDecimalRule implements ValidationRule
{
    protected $allowNumeric = false;

    public function __construct($allowNumeric = false)
    {
        $this->allowNumeric = $allowNumeric;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value instanceof \Condoedge\Finance\Casts\SafeDecimal) {
            return; // Already a SafeDecimal instance, no validation needed
        }

        if (!$this->allowNumeric && !is_numeric($value)) {
            $fail("The {$attribute} must be a valid positive number.");
            return;
        }
    }
}

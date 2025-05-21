<?php

namespace Condoedge\Finance\Casts;

class SafeDecimal
{
    private string $value;
    private int $scale;

    public function __construct(null|string|float|SafeDecimal $number, ?int $scale = null)
    {
        if (is_null($number)) {
            $number = '0';
        }

        if ($number instanceof SafeDecimal) {
            $number = (string) $number;
        }

        $this->scale = $scale ?? config('kompo-finance.decimal-scale');
        $this->value = bcadd((string) $number ?? '0', '0', $this->scale);
    }

    /**
     * Ensure the value is a SafeDecimal instance.
     */
    private function ensureSafeDecimal(mixed $value): SafeDecimal
    {
        if ($value instanceof SafeDecimal) {
            return $value;
        }
        
        return new self($value, $this->scale);
    }

    public function equals(mixed $other): bool
    {
        $other = $this->ensureSafeDecimal($other);
        return bccomp($this->value, $other->value, $this->scale) === 0;
    }

    public function greaterThan(mixed $other): bool
    {
        $other = $this->ensureSafeDecimal($other);
        return bccomp($this->value, $other->value, $this->scale) === 1;
    }

    public function lessThan(mixed $other): bool
    {
        $other = $this->ensureSafeDecimal($other);
        return bccomp($this->value, $other->value, $this->scale) === -1;
    }

    public function add(mixed $other): SafeDecimal
    {
        $other = $this->ensureSafeDecimal($other);
        return new self(bcadd($this->value, $other->value, $this->scale), $this->scale);
    }

    public function subtract(mixed $other): SafeDecimal
    {
        $other = $this->ensureSafeDecimal($other);
        return new self(bcsub($this->value, $other->value, $this->scale), $this->scale);
    }

    public function multiply(mixed $other): SafeDecimal
    {
        $other = $this->ensureSafeDecimal($other);
        return new self(bcmul($this->value, $other->value, $this->scale), $this->scale);
    }

    public function divide(mixed $other): SafeDecimal
    {
        $other = $this->ensureSafeDecimal($other);
        if ($other->equals(new self(0))) {
            throw new \DivisionByZeroError('Cannot divide by zero');
        }

        return new self(bcdiv($this->value, $other->value, $this->scale), $this->scale);
    }

    public function toFloat(): float
    {
        return (float) $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
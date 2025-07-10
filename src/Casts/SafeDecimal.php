<?php

namespace Condoedge\Finance\Casts;

use Illuminate\Contracts\Support\Arrayable;

class SafeDecimal implements \Stringable, Arrayable
{
    private string $value;
    private int $scale;

    public function __construct(mixed $number = '0', ?int $scale = null)
    {
        $this->scale = $scale ?? config('kompo-finance.decimal-scale', 2);
        $this->value = $this->normalizeValue($number);
    }

    private function normalizeValue(mixed $number): string
    {
        return match (true) {
            is_null($number) => '0',
            $number instanceof self => $number->value,
            is_array($number) => $number['safe_decimal_value'] ?? '0',
            default => bcadd((string) $number, '0', $this->scale)
        };
    }

    /**
     * Ensure the value is a SafeDecimal instance.
     */
    private function ensureSafeDecimal(mixed $value): SafeDecimal
    {
        if ($value instanceof self) {
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

    public function greaterThanOrEqual(mixed $other): bool
    {
        $other = $this->ensureSafeDecimal($other);
        return bccomp($this->value, $other->value, $this->scale) >= 0;
    }

    public function lessThanOrEqual(mixed $other): bool
    {
        $other = $this->ensureSafeDecimal($other);
        return bccomp($this->value, $other->value, $this->scale) <= 0;
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

    /**
     * If you're dividing a result that will give you a periodic decimal,
     * this method ensures the first division step is rounded up and the remaining ones will be rounded down.
     *
     * @param mixed $other
     *
     * @throws \DivisionByZeroError
     *
     * @return array<string, SafeDecimal>
     */
    public function preciseDivide(mixed $other): array
    {
        $other = $this->ensureSafeDecimal($other);
        if ($other->equals(new self(0))) {
            throw new \DivisionByZeroError('Cannot divide by zero');
        }

        $preciseDivision = bcdiv($this->value, $other->value, $this->scale + 1);
        $roundedUp = ceil($preciseDivision * pow(10, $this->scale))
            / pow(10, $this->scale);
        $roundedDown = floor($preciseDivision * pow(10, $this->scale))
            / pow(10, $this->scale);

        return [
            'first_division' => new self($roundedUp, $this->scale),
            'remaining_values' => new self($roundedDown, $this->scale),
        ];
    }

    public function divide(mixed $other): SafeDecimal
    {
        $other = $this->ensureSafeDecimal($other);
        if ($other->equals(new self(0))) {
            throw new \DivisionByZeroError('Cannot divide by zero');
        }

        return new self(bcdiv($this->value, $other->value, $this->scale), $this->scale);
    }

    public function abs(): SafeDecimal
    {
        return new self(abs($this->toFloat()), $this->scale);
    }

    public function negate(): SafeDecimal
    {
        return new self(bcmul($this->value, '-1', $this->scale), $this->scale);
    }

    public function round($precision): SafeDecimal
    {
        return new self(round($this->toFloat(), $precision), $precision);
    }

    public function floor($precision): SafeDecimal
    {
        $multiplier = pow(10, $precision);
        return new self(floor($this->toFloat() * $multiplier) / $multiplier, $precision);
    }

    public function toFloat(): float
    {
        return (float) $this->value;
    }

    public function get(): float
    {
        return $this->toFloat();
    }

    public function toArray()
    {
        return [
            'safe_decimal_value' => $this->value,
            'scale' => $this->scale,
        ];
    }

    public function __toString(): string
    {
        return $this->value;
    }
}

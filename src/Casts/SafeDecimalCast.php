<?php

namespace Condoedge\Finance\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use WendellAdriel\ValidatedDTO\Casting\Castable;
use WendellAdriel\ValidatedDTO\Exceptions\CastException;

class SafeDecimalCast implements CastsAttributes, Castable
{
    public function get($model, $key, $value, $attributes)
    {
        if (is_null($value)) {
            return null;
        }

        return new SafeDecimal($value);
    }

    public function set($model, $key, $value, $attributes)
    {
        if (is_null($value)) {
            return null;
        }

        return (string) (new SafeDecimal($value));
    }

    /**
     * @throws CastException
     */
    public function cast(string $property, mixed $value): SafeDecimal
    {
        if (! is_numeric($value) && $value !== '') {
            throw new CastException($property);
        }

        return new SafeDecimal($value);
    }
}
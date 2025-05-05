<?php

namespace Condoedge\Finance\Models\Dto;

use WendellAdriel\ValidatedDTO\Casting\StringCast;
use WendellAdriel\ValidatedDTO\Concerns\EmptyDefaults;
use WendellAdriel\ValidatedDTO\ValidatedDTO;

class CreateAddressDto extends ValidatedDTO
{
    use EmptyDefaults;

    public string $postal_code;
    public string $city;
    public string $state;
    public string $country;
    public string $address1;

    public function rules(): array
    {
        return [
            'city' => ['required', 'string'],
            'state' => ['required', 'string'],
            'country' => ['required', 'string'],
            'address1' => ['required', 'string'],
            'postal_code' => ['required', 'string'],
        ];
    }

    public function casts(): array
    {
        return [
            'city' => new StringCast,
            'state' => new StringCast,
            'country' => new StringCast,
            'address1' => new StringCast,
            'postal_code' => new StringCast,
        ];
    }
}
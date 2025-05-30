<?php

namespace Condoedge\Finance\Models\Dto\Vendors;

use Condoedge\Finance\Models\Dto\Customers\CreateAddressDto;
use WendellAdriel\ValidatedDTO\Casting\DTOCast;
use WendellAdriel\ValidatedDTO\Concerns\EmptyDefaults;
use WendellAdriel\ValidatedDTO\ValidatedDTO;

class CreateVendorFromCustomable extends ValidatedDTO
{
    use EmptyDefaults;

    /**
     * The customable object (the model that implements CustomableContract).
     * @var mixed
     */
    public $customable;

    public ?CreateAddressDto $address;

    public function casts(): array
    {
        return [
            'address' => new DTOCast(CreateAddressDto::class),
        ];
    }

    public function rules(): array
    {
        return [
            'customable' => ['required'],
            'address' => ['sometimes', 'array'],
            'address.address1' => ['required_with:address', 'string'],
            'address.city' => ['required_with:address', 'string'],
            'address.state' => ['required_with:address', 'string'],
            'address.postal_code' => ['required_with:address', 'string'],
            'address.country' => ['required_with:address', 'string'],
        ];
    }
}

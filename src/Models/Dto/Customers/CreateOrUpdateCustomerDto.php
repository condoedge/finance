<?php

namespace Condoedge\Finance\Models\Dto\Customers;

use WendellAdriel\ValidatedDTO\Casting\DTOCast;
use WendellAdriel\ValidatedDTO\Casting\StringCast;
use WendellAdriel\ValidatedDTO\Concerns\EmptyDefaults;
use WendellAdriel\ValidatedDTO\ValidatedDTO;

class CreateOrUpdateCustomerDto extends ValidatedDTO
{
    use EmptyDefaults;

    /**
     * Send this field as null to create a new customer.
     *
     * @var int
     */
    public ?int $id;

    public string $name;
    public string $email;

    public ?int $team_id;

    public ?CreateAddressDto $address;

    public function casts(): array
    {
        return [
            'name' => new StringCast(),
            'email' => new StringCast(),
            'address' => new DTOCast(CreateAddressDto::class),
        ];
    }

    public function rules(): array
    {
        return [
            /**
             * Send this field as null to create a new customer.
             *
             * @var int|null
             *
             * @example null
             */
            'id' => ['nullable', 'integer', 'exists:fin_customers,id'],
            'name' => ['required', 'string'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'team_id' => ['sometimes', 'integer', 'exists:teams,id'],
            'address' => ['required_without:id', 'array'],
            'address.address1' => ['required_with:address', 'string'],
            'address.city' => ['required_with:address', 'string'],
            'address.state' => ['required_with:address', 'string'],
            'address.postal_code' => ['required_with:address', 'string'],
            'address.country' => ['required_with:address', 'string'],
        ];
    }

    public function after($validator): void
    {
        if (!$validator->errors()->has('address') && $validator->errors()->has('address*')) {
            $validator->errors()->add('address', __('validation-customer-address-must-be-complete'));
        }
    }
}

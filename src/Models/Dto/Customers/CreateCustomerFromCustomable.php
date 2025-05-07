<?php

namespace Condoedge\Finance\Models\Dto\Customers;

use Condoedge\Finance\Facades\CustomerModel;
use Condoedge\Finance\Models\CustomableContract;
use WendellAdriel\ValidatedDTO\Casting\DTOCast;
use WendellAdriel\ValidatedDTO\Casting\IntegerCast;
use WendellAdriel\ValidatedDTO\Casting\StringCast;
use WendellAdriel\ValidatedDTO\Concerns\EmptyDefaults;
use WendellAdriel\ValidatedDTO\ValidatedDTO;

class CreateCustomerFromCustomable extends ValidatedDTO
{
    use EmptyDefaults;

    public int $customable_id;
    public string $customable_type;

    public ?int $team_id = null;

    public ?CreateAddressDto $address;

    public CustomableContract $customable;

    public function rules(): array
    {
        return [
            'customable_id' => ['required', 'integer'],
            'customable_type' => ['required', 'string', 'in:' . CustomerModel::getCustomables()->keys()->implode(',')],
            'team_id' => ['sometimes', 'integer', 'exists:teams,id'],
            'address' => ['sometimes', 'array'],
            'address.address1' => ['required_with:address', 'string'],
            'address.city' => ['required_with:address', 'string'],
            'address.state' => ['required_with:address', 'string'],
            'address.postal_code' => ['required_with:address', 'string'],
            'address.country' => ['required_with:address', 'string'],
        ];
    }

    public function casts(): array
    {
        return [
            'customable_id' => new IntegerCast,
            'customable_type' => new StringCast,
            'address' => new DTOCast(CreateAddressDto::class),
        ];
    }

    public function after($validator): void
    {
        $customableType = $this->dtoData['customable_type'] ?? null;
        $customableId = $this->dtoData['customable_id'] ?? null;
        $address = $this->dtoData['address'] ?? null;

        if ($customableType && $customableId) {
            $this->customable = getModelFromMorphable($customableType, $customableId);

            if (!$this->customable->getFirstValidAddress() && empty($address)) {
                $validator->errors()->add('address', __('translate.validation.customable.address-required'));
            }
        }
    }
}
<?php

namespace Condoedge\Finance\Models\Dto\PaymentTerms;

use Condoedge\Finance\Casts\SafeDecimal;
use Condoedge\Finance\Casts\SafeDecimalCast;
use Condoedge\Finance\Models\PaymentTermTypeEnum;
use Condoedge\Finance\Rule\SafeDecimalRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Facades\Validator as FacadesValidator;
use WendellAdriel\ValidatedDTO\Casting\ArrayCast;
use WendellAdriel\ValidatedDTO\Casting\EnumCast;
use WendellAdriel\ValidatedDTO\Casting\FloatCast;
use WendellAdriel\ValidatedDTO\Casting\IntegerCast;
use WendellAdriel\ValidatedDTO\Casting\StringCast;
use WendellAdriel\ValidatedDTO\ValidatedDTO;

/**
 * Data Transfer Object for creating a new payment term
 * 
 * This DTO is used to encapsulate the data required to create a new payment term,
 * including the type, name, description, and any additional settings.
 * 
 * @property PaymentTermTypeEnum $term_type The type of payment term (e.g., Installment, COD)
 * @property string $term_name The name of the payment term
 * @property string|null $term_description Optional description of the payment term
 * @property array $settings Additional settings for the payment term, such as installment periods or net terms
 */
class CreateOrUpdatePaymentTermDto extends ValidatedDTO
{
    public ?int $id;
    public PaymentTermTypeEnum $term_type;
    public string $term_name;
    public ?string $term_description;

    public ?array $settings;

    /**
     * Validation rules for creating a product
     */
    public function rules(): array
    {
        return [
            'id' => 'nullable|integer|exists:fin_payment_terms,id',
            'term_type' => 'required|integer|in:' . collect(PaymentTermTypeEnum::cases())->pluck('value')->implode(','),
            'term_name' => 'required|string|max:100',
            'term_description' => 'nullable|string|max:1000',
            'settings' => 'nullable|array',
        ];
    }

    public function casts(): array
    {
        return [
            'term_type' => new EnumCast(PaymentTermTypeEnum::class),
            'term_name' => new StringCast(),
            'term_description' => new StringCast(),
            'settings' => new ArrayCast(),
        ];
    }

    public function defaults(): array
    {
        return [
            'settings' => [],
        ];
    }

    public function after(Validator $validator): void
    {
        $settings = $this->dtoData['settings'] ?? [];
        $paymentTermType = $this->dtoData['term_type'] ?? null;

        if ($paymentTermType) {
            // Validate settings based on the payment term type
            $rules = PaymentTermTypeEnum::from($paymentTermType)->settingsRules();

            if ((count($rules) > 0 && empty($settings)) || FacadesValidator::make($settings, $rules)->fails()) {
                $validator->errors()->add('settings', __('translate.invalid-settings'));
            }
        }
    }
}

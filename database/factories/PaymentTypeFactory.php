<?php

namespace Condoedge\Finance\Database\Factories;

use Condoedge\Finance\Models\PaymentMethod;
use Condoedge\Finance\Models\PaymentMethodEnum;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Condoedge\Finance\Models\PaymentMethod>
 */
class PaymentTypeFactory extends Factory
{
    protected $model = PaymentMethod::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        $enum = $this->faker->randomElement(PaymentMethodEnum::cases());

        return [
            'id' => $enum->value,
            'name' => $enum->label(),
            'code' => $enum->code(),
        ];
    }
}

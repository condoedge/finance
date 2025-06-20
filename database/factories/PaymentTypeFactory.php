<?php

namespace Condoedge\Finance\Database\Factories;

use Condoedge\Finance\Models\PaymentType;
use Condoedge\Finance\Models\PaymentTypeEnum;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Condoedge\Finance\Models\PaymentType>
 */
class PaymentTypeFactory extends Factory
{
    protected $model = PaymentType::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        $enum = $this->faker->randomElement(PaymentTypeEnum::cases());

        return [
            'id' => $enum->value,
            'name' => $enum->label(),
            'payment_gateway' => $enum->getPaymentGateway(),
        ];
    }
}

<?php

namespace Condoedge\Finance\Database\Factories;

use Condoedge\Finance\Models\PaymentTerm;
use Condoedge\Finance\Models\PaymentTermTypeEnum;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Condoedge\Finance\Models\PaymentTerm>
 */
class PaymentTermFactory extends Factory
{
    protected $model = PaymentTerm::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'term_name' => $this->faker->unique()->word(),
            'term_description' => $this->faker->sentence(),
            'term_type' => PaymentTermTypeEnum::COD,
            'settings' => [],
        ];
    }
}

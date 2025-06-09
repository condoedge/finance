<?php

namespace Condoedge\Finance\Database\Factories;

use Condoedge\Finance\Models\GlAccountSegmentType;
use Condoedge\Finance\Models\GlAccountSegmentTypeEnum;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Condoedge\Finance\Models\GlAccountSegmentType>
 */
class GlAccountSegmentTypeFactory extends Factory
{
    protected $model = GlAccountSegmentType::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        $enum = $this->faker->randomElement(GlAccountSegmentTypeEnum::cases());

        return [
            'id' => $enum->value,
            'name' => $enum->label(),
        ];
    }
}

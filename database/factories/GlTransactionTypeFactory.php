<?php

namespace Database\Factories;

use Condoedge\Finance\Models\GlTransactionType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * GL Transaction Type Factory
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Condoedge\Finance\Models\GlTransactionType>
 */
class GlTransactionTypeFactory extends Factory
{
    protected $model = GlTransactionType::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->word(),
            'label' => $this->faker->sentence(3),
            'code' => $this->faker->unique()->strtoupper($this->faker->lexify('???')),
            'fiscal_period_field' => 'is_open_' . strtolower($this->faker->lexify('??')),
            'allows_manual_entry' => $this->faker->boolean(),
            'description' => $this->faker->sentence(),
            'sort_order' => $this->faker->numberBetween(1, 100),
            'is_active' => true,
        ];
    }

    /**
     * Create a manual GL transaction type
     */
    public function manualGl(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'manual_gl',
            'label' => 'Manual GL',
            'code' => 'GL',
            'fiscal_period_field' => 'is_open_gl',
            'allows_manual_entry' => true,
            'sort_order' => 1,
        ]);
    }

    /**
     * Create a bank transaction type
     */
    public function bank(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'bank',
            'label' => 'Bank Transaction',
            'code' => 'BNK',
            'fiscal_period_field' => 'is_open_bnk',
            'allows_manual_entry' => false,
            'sort_order' => 2,
        ]);
    }

    /**
     * Create an accounts receivable transaction type
     */
    public function receivable(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'receivable',
            'label' => 'Accounts Receivable',
            'code' => 'AR',
            'fiscal_period_field' => 'is_open_rm',
            'allows_manual_entry' => false,
            'sort_order' => 3,
        ]);
    }

    /**
     * Create an accounts payable transaction type
     */
    public function payable(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'payable',
            'label' => 'Accounts Payable',
            'code' => 'AP',
            'fiscal_period_field' => 'is_open_pm',
            'allows_manual_entry' => false,
            'sort_order' => 4,
        ]);
    }
}

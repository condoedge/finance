<?php

namespace Condoedge\Finance\Database\Factories;

use Condoedge\Finance\Models\GlAccount;
use Condoedge\Finance\Models\Product;
use Condoedge\Finance\Models\ProductTypeEnum;
use Illuminate\Database\Eloquent\Factories\Factory;
use Kompo\Auth\Database\Factories\TeamFactory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition()
    {
        return [
            'product_name' => $this->faker->word,
            'default_revenue_account_id' => GlAccount::factory(),
            'product_type' => ProductTypeEnum::PRODUCT_COST,
            'product_description' => $this->faker->sentence,
            'product_cost' => $this->faker->randomFloat(2, 1, 1000),
            'team_id' => TeamFactory::new()->create()->id,
        ];
    }
}

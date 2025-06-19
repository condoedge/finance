<?php

namespace Condoedge\Finance\Database\Factories;

use Condoedge\Finance\Models\GlAccount;
use Condoedge\Finance\Models\Invoice;
use Condoedge\Finance\Models\InvoiceDetail;
use Condoedge\Finance\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceDetailFactory extends Factory
{
    protected $model = InvoiceDetail::class;

    public function definition()
    {
        $quantity = $this->faker->numberBetween(1, 10);
        $unit_price = $this->faker->randomFloat(2, 10, 100);
        
        return [
            'name' => $this->faker->word,
            'invoice_id' => Invoice::factory(),
            'revenue_account_id' => GlAccount::factory(),
            'product_id' => Product::factory(),
            'quantity' => $quantity,
            'description' => $this->faker->sentence,
            'unit_price' => $unit_price,
            // extended_price is a computed column (quantity * unit_price)
            // It's included here for documentation purposes, but will be calculated by the database
            //'extended_price' => $quantity * $unit_price,
        ];
    }
}

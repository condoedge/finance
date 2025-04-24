<?php

namespace Condoedge\Finance\Database\Factories;

use Condoedge\Finance\Facades\InvoiceTypeEnum;
use Condoedge\Finance\Facades\PaymentTypeEnum;
use Condoedge\Finance\Models\Account;
use Condoedge\Finance\Models\Customer;
use Condoedge\Finance\Models\Invoice;
use Condoedge\Finance\Models\Tax;
use Condoedge\Finance\Models\TaxGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition()
    {
        return [
            'invoice_number' => $this->faker->unique()->randomNumber(8, false),
            'invoice_date' => $this->faker->date(),
            'invoice_amount_before_taxes' => $this->faker->randomFloat(2, 100, 1000),
            'invoice_due_date' => $this->faker->dateTimeBetween('now', '+1 month'),
            'invoice_due_amount' => $this->faker->randomFloat(2, 50, 500),
            'invoice_tax_amount' => $this->faker->randomFloat(2, 10, 100),
            'customer_id' => Customer::factory(),
            'invoice_type_id' => collect(InvoiceTypeEnum::cases())->random()->value,
            'payment_type_id' => collect(PaymentTypeEnum::cases())->random()->value,
            'account_receivable_id' => Account::factory(),
            'tax_group_id' => TaxGroup::factory(),
        ];
    }
}

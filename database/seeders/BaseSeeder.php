<?php

namespace Condoedge\Finance\Database\Seeders;

use Condoedge\Finance\Models\Customer;
use Condoedge\Finance\Models\Invoice;
use Condoedge\Finance\Models\InvoiceApply;
use Condoedge\Finance\Models\InvoiceDetail;
use Condoedge\Finance\Models\Tax;
use Condoedge\Finance\Models\TaxGroup;
use Illuminate\Database\Seeder;

class BaseSeeder extends Seeder
{
    public function run()
    {
        Customer::factory()->count(10)->create();
        Invoice::factory()->count(10)->create();
        InvoiceDetail::factory()->count(10)->create();
        InvoiceApply::factory()->count(10)->create();
        TaxGroup::factory()->count(10)->create();
        Tax::factory()->count(10)->create();

        TaxGroup::all()->each(function ($taxGroup) {
            $taxGroup->taxes()->attach(Tax::all()->random(2)->pluck('id'));
        });
    }
}

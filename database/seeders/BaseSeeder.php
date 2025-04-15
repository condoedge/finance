<?php

namespace Condoedge\Finance\Database\Seeders;

use Illuminate\Database\Seeder;
use Condoedge\Finance\Models\Tax;
use Condoedge\Finance\Models\Invoice;
use Condoedge\Finance\Models\Customer;
use Condoedge\Finance\Models\TaxGroup;
use Condoedge\Finance\Models\InvoiceDetail;
use Condoedge\Finance\Models\InvoicePayment;

class BaseSeeder extends Seeder
{
    public function run()
    {
        Customer::factory()->count(10)->create();
        Invoice::factory()->count(10)->create();
        InvoiceDetail::factory()->count(10)->create();
        InvoicePayment::factory()->count(10)->create();
        TaxGroup::factory()->count(10)->create();
        Tax::factory()->count(10)->create();

        TaxGroup::all()->each(function ($taxGroup) {
            $taxGroup->taxes()->attach(Tax::all()->random(2)->pluck('id'));
        });
    }
}

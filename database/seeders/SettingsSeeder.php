<?php

namespace Condoedge\Finance\Database\Seeders;

use Condoedge\Finance\Facades\InvoiceTypeEnum;
use Illuminate\Database\Seeder;
use Condoedge\Finance\Models\InvoiceType;

class SettingsSeeder extends Seeder
{
    public function run()
    {
        collect(InvoiceTypeEnum::cases())->each(function ($enum) {
            $type = new InvoiceType();

            $type->id = $enum->value;
            $type->name = $enum->label();
            $type->prefix = $enum->prefix();
            $type->save();
        });
    }
}

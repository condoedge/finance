<?php

namespace Condoedge\Finance\Database\Seeders;

use Condoedge\Finance\Facades\InvoiceTypeEnum;
use Condoedge\Finance\Models\InvoiceStatus;
use Condoedge\Finance\Models\InvoiceStatusEnum;
use Illuminate\Database\Seeder;
use Condoedge\Finance\Models\InvoiceType;

class SettingsSeeder extends Seeder
{
    public function run()
    {
        collect(InvoiceTypeEnum::cases())->each(function ($enum) {
            $type = new InvoiceType();

            if (InvoiceType::find($enum->value)) {
                return null;
            }

            $type->id = $enum->value;
            $type->name = $enum->label();
            $type->prefix = $enum->prefix();
            $type->sign_multiplier = $enum->signMultiplier();
            $type->next_number = 1;
            $type->save();
        });

        collect(InvoiceStatusEnum::cases())->each(function ($enum) {
            $type = new InvoiceStatus();

            if (InvoiceStatus::find($enum->value)) {
                return null;
            }

            $type->id = $enum->value;
            $type->name = $enum->label();
            $type->save();
        });
    }
}

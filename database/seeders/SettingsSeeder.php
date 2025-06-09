<?php

namespace Condoedge\Finance\Database\Seeders;

use Condoedge\Finance\Facades\InvoiceTypeEnum;
use Condoedge\Finance\Models\GlAccountSegmentType;
use Condoedge\Finance\Models\GlAccountSegmentTypeEnum;
use Condoedge\Finance\Models\InvoiceStatus;
use Condoedge\Finance\Models\InvoiceStatusEnum;
use Condoedge\Finance\Models\PaymentType;
use Condoedge\Finance\Models\PaymentTypeEnum;
use Illuminate\Database\Seeder;
use Condoedge\Finance\Models\InvoiceType;
use Condoedge\Finance\Models\InvoiceTypeEnum as ModelsInvoiceTypeEnum;

class SettingsSeeder extends Seeder
{
    public function run()
    {
        collect(InvoiceTypeEnum::getEnumClass()::cases())->each(function (ModelsInvoiceTypeEnum $enum) {
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

        collect(PaymentTypeEnum::cases())->each(function ($enum) {
            $type = new PaymentType();

            if (PaymentType::find($enum->value)) {
                return null;
            }

            $type->id = $enum->value;
            $type->name = $enum->label();
            $type->payment_gateway = $enum->getPaymentGateway();
            $type->save();
        });

        // collect(GlAccountSegmentTypeEnum::cases())->each(function ($enum) {
        //     $type = new GlAccountSegmentType();

        //     if (GlAccountSegmentType::find($enum->value)) {
        //         return null;
        //     }

        //     $type->id = $enum->value;
        //     $type->name = $enum->label();
        //     $type->save();
        // });
    }
}
